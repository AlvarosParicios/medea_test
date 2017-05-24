<?php

/**
 * Created by PhpStorm.
 * User: jtriana
 * Date: 20/08/2015
 * Time: 12:16 PM
 */
class FileDownloaderCommand extends CConsoleCommand
{
    // Constantes de la aplicacion
    const ACC_UPLOAD = 7;

    private $array_cfg = array();
    private $array_status = array();
    private $filedate;

    public $verbose_mode = false;

    public function run($args){

        foreach($args as $arg){
            print_r($arg);
            echo"\n---\n";
        }

        Logger::log(CLogger::LEVEL_INFO, "--- INICIO EJECUCION FILE DOWNLOADER","filedown",$this->verbose_mode);

        try{
            //En primer lugar obtenemos los datos de configuracion de la tabla file_downloader config
            $this->array_cfg = $this->getConfig();

            //Obtenemos los status del file downloader
            $this->array_status = $this->getStatus();

            //Obtenemos la fecha del fichero
            $this->filedate = $this->readDate_mod5();

            //Generamos la entrada del fichero en la Base de Datos
            $this->generateFilenamesDB();

            //Por ultimo intentamos traer los ficheros por wget del HUB a nuestro repositorio.
            $this->getFiles();
        }
        catch(Exception $e){

        }
        /*
        $cnx=conecta_BDmaestra ();
        if (readConfigDB($cnx, $array_cfg, $array_stat))  {
            $filedate=readDate_mod5();
            $numfiles=generateFilenamesDB($cnx);
            if ($numfiles)  {
                getFiles($cnx);
            }
        }
        else  {
            echo "error de lectura de la Configuracion";
        }
        */

    }

    private function getConfig(){
        $rows = FileDownloaderConfig::model()->findAll();
        $arr_tmp = array();
        foreach($rows as $row ){
            $arr_tmp[$row['varname']] = $row['value'];
        }
        return $arr_tmp;
    }

    private function getStatus(){
        $rows = FileDownloaderStatus::model()->findAll();
        $arr_tmp = array();
        foreach($rows as $row ){
            $arr_tmp[$row['id']] = $row['literal'];
        }
        return $arr_tmp;
    }

    private function readDate_mod5()
    {     //TODAS LAS HORAS respecto a TZ 'UTC'

        //le quito 5 minutos a la hora actual en UTC
        $fecha_utc=gmdate('YmdHi', time() - 300);
        $ultcifra=substr($fecha_utc,strlen($fecha_utc)-1);
        $min2cifra=0;
        if ($ultcifra >= 5) {
            $min2cifra=5;
        }
        $fecha_utc_mod5=substr($fecha_utc,0,strlen($fecha_utc)-1) .$min2cifra ;
        return  $fecha_utc_mod5;
    }

    private function generateFilenamesDB()
    {
        try{
            $numfiles=0;

            $array_isp = explode(",", $this->array_cfg['ISP_hub']);
            for ($i=0; $i < count($array_isp); $i++)  {
                $filename=$this->array_cfg['header_inf_hub'].$this->array_cfg['period_hub'] ."-" .$array_isp[$i] ."-" .$this->filedate . ".csv" ;
                //Comprobamos si el fichero ya ha sido insertado con estado PENDING para no volver a hacerlo

                $count_tmp = count(FileDownloaderData::model()->findAllByAttributes(array('filename'=>$filename,'status'=>FileDownloaderStatus::PENDING)));
                if(!$count_tmp){
                    $file_data = new FileDownloaderData();
                    $file_data->filename = $filename;
                    $file_data->full_path_file = "n.a.";
                    $file_data->isp_id = $array_isp[$i];
                    $file_data->status = FileDownloaderStatus::PENDING;
                    $file_data->insertion_date = date("Y-m-d H:i:s");
                    $file_data->retry = 0;
                    if($file_data->save()){
                        Logger::log(CLogger::LEVEL_INFO, "Fichero insertado correctamente: ".$filename,"filedown",$this->verbose_mode);
                    }
                    else{
                        Logger::log(CLogger::LEVEL_ERROR, "Problemas al insertar el fichero en BD: ".$filename,"filedown",$this->verbose_mode);
                        Logger::log(CLogger::LEVEL_ERROR, "Error: ".var_export($file_data->getErrors(),true),"filedown",$this->verbose_mode);
                    }
                }

                else{
                    Logger::log(CLogger::LEVEL_INFO, "Fichero generado previamente, no se inserta de nuevo: ".$filename,"filedown",$this->verbose_mode);
                }

            }  //end for

        }
        catch(Exception $e){
            print_r("\n CASCO: ".var_export($e->getMessage(),true));
            Logger::log(CLogger::LEVEL_ERROR, "Error: ".var_export($e,true),"filedown",$this->verbose_mode);
        }


    }

    private function getFiles()
    {
        try{
            $user=$this->array_cfg['user_hub'];
            $pass=$this->array_cfg['pass_hub'];
            $wget_retry=$this->array_cfg['wget_retry'];

            $pathcmd="https://" .$this->array_cfg['hostname_hub'] . "/" .$this->array_cfg['path_reports_hub']. "/" .$this->array_cfg['path_inftype_hub'] . $this->array_cfg['period_hub'] ;
            $pathlog=$this->array_cfg['HOME_medea']."/" .$this->array_cfg['path_LOG_files'];
            $pathtmp=$this->array_cfg['HOME_medea']."/" .$this->array_cfg['path_TMP_files'];
            $timeout=$this->array_cfg['timeout_fd'];

            $isp_last="";
            $filedate_new="";

            $connection=Yii::app()->db;

            $skipped_oldOK_isp=false;
            $downOKstatus="status=" .FileDownloaderStatus::DOWNLOAD_OK. " AND downloaded_date is not null";
            $wherestatus="status=" .FileDownloaderStatus::PENDING. " OR status=".FileDownloaderStatus::DOWNLOAD_ERROR;
            $query = "SELECT filename, retry FROM file_downloader_data WHERE $wherestatus ORDER BY isp_id ASC, filename DESC";


            $command=$connection->createCommand($query);
            $result = $command->queryAll();

            //BUCLE FICHEROS
            for ($i=0; $i < count($result); $i++)  {
                $filename= $result[$i]['filename'];
                $fullpathfilename="";

                $retry= $result[$i]['retry'] +1;
                $fdatedown="null ";
                $fstatus=FileDownloaderStatus::PENDING;

                $filelog= "wget_" . substr($filename,0,strlen($filename)-4) . ".log";
                $cmd="wget --no-check-certificate  --http-user='$user'  --http-password='$pass' --tries=$wget_retry  --timeout=$timeout  $pathcmd/$filename -O  $pathtmp/$filename  -o  $pathlog/$filelog " ;

                $filename_parts=explode("-",$filename);
                $isp=$filename_parts[2];
                $filedate_curr=substr($filename_parts[3],0,strlen($filename_parts[3])-4);
                if ($isp_last != $isp)  {
                    $isp_last=$isp;
                    $filedate_new="";
                    //echo "\n ---------- CAMBIO ISP: $isp_last \n ";
                    Logger::log(CLogger::LEVEL_INFO, "TRATANDO FICHEROS (PENDING O DOWNLOAD_ERROR) DEL ISP:".$isp_last,"filedown",$this->verbose_mode);
                    $newest=false;
                    $skipped_oldOK_isp=false;
                }

                if ($newest == true) {
                    $fstatus=FileDownloaderStatus::SKIPPED;
                }
                else  {
                    //Obtenemos el fichero por wget y lo copiamos al directorio correspondiente
                    Logger::log(CLogger::LEVEL_INFO, "Obtener fichero: ".$filename. " -- shell_exec: ".$cmd,"filedown",$this->verbose_mode);
                    shell_exec("$cmd  2>&1 ");

                    //Leemos el contenido del fichero
                    $log=$this->file_read($filelog,$pathlog);

                    $HTTPtout=strpos($log, $this->array_cfg['http_TIMEOUT']);
                    if ($HTTPtout !== false) {
                        Logger::log(CLogger::LEVEL_ERROR, "READ FILE_LOG: http_TIMEOUT ","filedown",$this->verbose_mode);
                        $fstatus=FileDownloaderStatus::DOWNLOAD_ERROR;
                    }
                    else  {
                        $HTTP200=strpos($log, $this->array_cfg['http_OK']);
                        if ($HTTP200 !== false) {
                            Logger::log(CLogger::LEVEL_INFO, "READ FILE_LOG: http_OK ","filedown",$this->verbose_mode);
                            $fstatus=FileDownloaderStatus::DOWNLOAD_OK;
                            if ( $this->file_check_integrity ($filename,$pathtmp) )  {
                                $retry -- ;
                                $fdatedown="now() ";
                                //ASIGNAR como el mas reciente para el actual ISP
                                if ($filedate_curr > $filedate_new )  {
                                    $filedate_new=$filedate_curr;
                                    $newest=true;
                                }
                                $OKdel=$this->file_delete($filelog,$pathlog);
                                $OKmov=$this->file_move($filename,$this->array_cfg['HOME_medea']."/" .$this->array_cfg['path_TMP_files'],$this->array_cfg['HOME_medea']."/" .$this->array_cfg['path_IN_files']);
                                if ($OKmov)  {
                                    $fullpathfilename=$this->array_cfg['HOME_medea']."/" .$this->array_cfg['path_IN_files']."/" .$filename;
                                    if ($skipped_oldOK_isp == false)  {
                                        $query3 = "UPDATE file_downloader_data SET status=" .FileDownloaderStatus::SKIPPED . " WHERE downloaded_date < " .$fdatedown . "  AND isp_id = " .$isp_last ." AND (" .$downOKstatus . ")";
                                        $command=$connection->createCommand($query3);
                                        $res3 = $command->query();

                                        //Lo sacamos del if de abajo porque si no ha ido bien el update no llegara aqui
                                        //porque saldra por el catch
                                        $skipped_oldOK_isp=true;

                                        /*
                                        $nrowupd3= $res3 ? mysqli_affected_rows($DBcnx) : 0;
                                        if ($res3 !== false)  {
                                            $skipped_oldOK_isp=true;
                                        }
                                        */
                                    }
                                    Logger::log(CLogger::LEVEL_INFO, "Updated query 3 OK: ".$query3,"filedown",$this->verbose_mode);
                                    Logger::log(CLogger::LEVEL_INFO, "Registros actualizados: ".count($res3),"filedown",$this->verbose_mode);
                                    //echo "\n$query3 OK\n nrows:$nrowupd3\n\n";

                                }
                            }
                            else  {  //$HTTP200 === false
                                $fstatus=FileDownloaderStatus::DOWNLOAD_ERROR;
                                Logger::log(CLogger::LEVEL_ERROR, "READ FILE_LOG: DOWNLOAD_ERROR ","filedown",$this->verbose_mode);

                            }
                        }
                        else  {
                            Logger::log(CLogger::LEVEL_INFO, $filelog." NOT FOUND","filedown",$this->verbose_mode);
                        }
                    }
                }
                $query2 = "UPDATE file_downloader_data SET downloaded_date=" .$fdatedown . ", retry=" .$retry . ", status=" .$fstatus . ", full_path_file='" .$fullpathfilename . "' WHERE filename='" .$filename . "' AND (" .$wherestatus . ")";
                $command=$connection->createCommand($query2);
                $res2 = $command->query();
                Logger::log(CLogger::LEVEL_INFO, "Updated query 2 OK: ".$query2,"filedown",$this->verbose_mode);
                Logger::log(CLogger::LEVEL_INFO, "Registros actualizados: ".count($res2),"filedown",$this->verbose_mode);
                //$res2= mysqli_query($DBcnx, $query);
                //$nrowupd= $res2 ? mysqli_affected_rows($DBcnx) : 0;
                //echo "$query \n nrow:$nrowupd \n\n";

            }  //end for
        }
        catch(Exception $e){
            print_r("\n CASCO: ".var_export($e->getMessage(),true));
            Logger::log(CLogger::LEVEL_ERROR, "Error: ".var_export($e,true),"filedown",$this->verbose_mode);
        }

    }

    private function file_check_integrity($filename, $path) {
        $OK=true;
        try {
            $file = file($path ."/".$filename);
            $nlines=count($file);
            $header_fields=explode(",",$file[1]);
            $nlines_header=$header_fields[0];
            //echo "\ncabecera:$file[1] \n nlines:$nlines  nlines_head:$nlines_header \n\n";
            Logger::log(CLogger::LEVEL_INFO, "cabecera: ".$file[1]." -- nlines: ".$nlines." -- nlines_head: ".$nlines_header,"filedown",$this->verbose_mode);
            if ($nlines != $nlines_header+4)  {
                $OK=false;
            }
        }
        catch  (Exception $e) {
            $OK=false;
        }
        return $OK ;
    }

    private function file_read($filelog, $path) {
        //Lo de abajo funciona igual que la @ delante de la funcion
        //error_reporting (5);
        try {
            $data=@file_get_contents($path ."/" .$filelog );
            if ($data===FALSE){
                Logger::log(CLogger::LEVEL_ERROR, "Error 1 en file_get_contents del file_read","filedown",$this->verbose_mode);
            }

        }
        catch(Exception $e) {
            Logger::log(CLogger::LEVEL_ERROR, "Error 2 en file_get_contents file_read","filedown",$this->verbose_mode);
            $data=FALSE;
        }
        return $data ;
    }

    private function file_delete($filename, $path) {
        $OK=true;
        try {
            if (file_exists ($path ."/" .$filename)) {
                $OK=unlink($path ."/" .$filename) ;
                Logger::log(CLogger::LEVEL_INFO, "Deleted file: ".$filename,"filedown",$this->verbose_mode);
            }
        }
        catch  (Exception $e) {
            $OK=false;
        }
        return $OK ;
    }

    private function file_move($filename, $pathorig, $pathdest) {
        $OK=false;
        try {
            $OK1=file_exists ($pathorig);
            if ( !$OK1 ) {
                $OK1=mkdir($pathorig);
            }
            $OK2=file_exists ($pathdest);
            if ( !$OK2 ) {
                $OK2=mkdir($pathdest);
            }
            if ($OK1 && $OK2)  {
                $OK=rename($pathorig ."/" .$filename, $pathdest."/" .$filename) ;
                echo "\nmoviendo " .$pathorig ."/" .$filename ." -> " .$pathdest ."/" .$filename ."\n";
            }
        }
        catch  (Exception $e) {
            $OK=false;
        }
        return $OK ;
    }


}