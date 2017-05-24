
<?php

/*  * PHP file_downloader
  * JJAR
  * Uso:

	1-Incluir las constantes (nombres de Tablas propias, etc):
	include_once('../lib/constantes.php');

	2-obtener $fecha (GMT en formato YYYYMMDDHHMI) con MInutos en módulo 5. Es decir, solo son posibles YYYYMMDDHHM0 ó YYYYMMDDHHM5 

	3-Creamos el objeto FD_config para leer las filas con las "constantes" que nos interesan en Base de Datos (tabla file_downloader_config);
		recuperar variables de configuración:
		$user_hub, $pass_hub , $hostname _hub, (usuario y clave correspondientes a la máquina hostname_hub)
		$path_reports_hub , $path_inftype_hub, $period_hub, $cabec_inf_hub, $ISP_hub
		$ruta_entrada_Check_Quotas

	4-Generar los $nomb_fichero posibles (para los ISP configurados) incorporando $fecha obtenida en paso 2
		y las variables de config, y almacenarlos en la tabla file_downloader_data:

		foreach $ISP_hub {
		  Insertar entrada en Files_HUB con los campos: 
			filename: $cabec_inf_hub$period_hub-$ISP-$fecha.csv  
			date_down a NULL  (todos los campos date_* a NULL), status=0 (pendiente) , retry_down=0 
		}

	5-Ejecutar comandos para recuperar los ficheros más recientes desde la anterior recuperación exitosa:

		foreach $nomb_fichero (leido de tabla file_downloader_files, donde date_down es NULL , ordenadas desc por  date_insert)   {
			wget --no-check-certificate  --http-user="$user_hub"  --http-password="$pass_hub" https://$maquina_hub/$path_reports_hub/$ path_inftype_hub$period_hub/$nomb_fichero
			a) SI recuperado OK: 
			  a1) actualizar en tabla file_downloader_files donde filename=$nomb_fichero  los campos: status=1 (recuperado)  y date_down al timestamp
			  a2) mover $nomb_fichero a la $ruta_IN_Check_Quotas
			  a3) actualizar en tabla file_downloader_files donde filename=$nomb_fichero , los campos: status=3 (descartado) para los sucesivos ficheros con date_insert menor, que aún no tuvieran rellena date_down . Actualizar date_down al timestamp para no reintentar posteriormente. Y salir del bucle foreach.
			b) Si no OK, actualizar en tabla file_downloader_files donde filename=$nomb_fichero , el campo: 
			  retry_down (incrementando +1)
}

*/ 

  //incluidas aqui y en index.php para persistencia de SESSION
  ini_set('display_errors', 0);
  ini_set('max_execution_time', 10000); //milisegundos

  include_once('lib/constants.php');
  include_once('lib/connections.php');
  include_once('lib/functions.php');
  //include_once($libdir . 'form_generico.php');


    function readDate_mod5()
    {     //TODAS LAS HORAS respecto a TZ 'UTC'
        global $array_cfg;
        //date_default_timezone_set('Etc/UTC');
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

    function generateFilenamesDB($DBcnx)
    {    //CONSTANTES IMPORTADAS
        global $tabla_FDdata, $filedate;
         //CONFIG LEIDA BD
        global $array_cfg, $array_stat;

        $numfiles=0;
        $array_isp = explode(",", $array_cfg['ISP_hub']);
        for ($i=0; $i < count($array_isp); $i++)  {
          $filename=$array_cfg['header_inf_hub'].$array_cfg['period_hub'] ."-" .$array_isp[$i] ."-" .$filedate . ".csv" ;

          $query = "SELECT id FROM " .$tabla_FDdata . " WHERE filename='" .$filename . "' AND status=" .$array_stat['PENDING'] ;
          $result= mysqli_query($DBcnx, $query);
          $nrowsel= $result ? mysqli_num_rows($result) : 0;
          if ($result !== false && $nrowsel == 0) {
              $query = "INSERT IGNORE INTO " .$tabla_FDdata . " (filename,isp_id,status,insertion_date,retry)  VALUES ('" .$filename . "', " .$array_isp[$i]. ", " .$array_stat['PENDING'] . ", now(), 0 ) ";
              $result= mysqli_query($DBcnx, $query);
              $numins= $result ? mysqli_affected_rows($DBcnx) : 0;
              $numfiles += $numins;
        //echo "-----------\n $query \n nfilas:$numins\n";
          }
          else  {
              $numfiles=$nrowsel;
              echo "-- Fichero " .$filename ." generado previamente, no se inserta de nuevo\n";
          }
        }  //end for
        mysqli_free_result($result);

        return $numfiles;
    }


    function getFiles($DBcnx)
    {    //CONSTANTES IMPORTADAS
        global $tabla_FDdata, $filedate, $linuxver;
         //CONFIG LEIDA BD
        global $array_cfg, $array_stat;

        $user=$array_cfg['user_hub'];  
        $pass=$array_cfg['pass_hub'];
        $wget_retry=$array_cfg['wget_retry'];

        $pathcmd="https://" .$array_cfg['hostname_hub'] . "/" .$array_cfg['path_reports_hub']. "/" .$array_cfg['path_inftype_hub'] . $array_cfg['period_hub'] ;
        $pathlog=$array_cfg['HOME_medea']."/" .$array_cfg['path_LOG_files'];
        $pathtmp=$array_cfg['HOME_medea']."/" .$array_cfg['path_TMP_files'];
        $timeout=$array_cfg['timeout_fd'];

        $isp_last="";
        $filedate_new="";

        $skipped_oldOK_isp=false;
        $downOKstatus="status=" .$array_stat['DOWNLOAD_OK'] . " AND downloaded_date is not null";
        $wherestatus="status=" .$array_stat['PENDING'] . " OR status=" .$array_stat['DOWNLOAD_ERROR'];
        $query = "SELECT filename, retry FROM " .$tabla_FDdata . " WHERE $wherestatus ORDER BY isp_id ASC, filename DESC";
        $result= mysqli_query($DBcnx, $query);
        $nrowsel= $result ? mysqli_num_rows($result) : 0;
        //BUCLE FICHEROS
        for ($i=0; $i < $nrowsel; $i++)  {
            $row = mysqli_fetch_row($result);
            $filename= $row[0];
            $fullpathfilename="";

            $retry= $row[1] +1;
            $fdatedown="null ";
            $fstatus=$array_stat['PENDING'];

            $filelog= "wget_" . substr($filename,0,strlen($filename)-4) . ".log";
            $cmd="wget --no-check-certificate  --http-user='$user'  --http-password='$pass' --tries=$wget_retry  --timeout=$timeout  $pathcmd/$filename -O  $pathtmp/$filename  -o  $pathlog/$filelog " ;

            $filename_parts=explode("-",$filename);
            $isp=$filename_parts[2];
            $filedate_curr=substr($filename_parts[3],0,strlen($filename_parts[3])-4);
            if ($isp_last != $isp)  {
                $isp_last=$isp;
                $filedate_new="";
       echo "\n ---------- CAMBIO ISP: $isp_last \n ";
                $newest=false;
                $skipped_oldOK_isp=false;
            }

            if ($newest == true) {
                $fstatus=$array_stat['SKIPPED'];
            }
            else  {
       echo " ---> obtener fichero:\n  $cmd \n ";
                shell_exec("$cmd  2>&1 ");
                $log=file_read($filelog,$pathlog);

                $HTTPtout=strpos($log, $array_cfg['http_TIMEOUT']);
                if ($HTTPtout !== false) {
                    $fstatus=$array_stat['DOWNLOAD_ERROR'];
                }
                else  {
                  $HTTP200=strpos($log, $array_cfg['http_OK']);
                  if ($HTTP200 !== false) {
                    $fstatus=$array_stat['DOWNLOAD_OK'];
                    if ( file_check_integrity ($filename,$pathtmp) )  {
                        $retry -- ;
                        $fdatedown="now() ";
                          //ASIGNAR como el mas reciente para el actual ISP
                        if ($filedate_curr > $filedate_new )  {
                            $filedate_new=$filedate_curr;
                            $newest=true;
                        }
                        $OKdel=file_delete($filelog,$pathlog);
                        $OKmov=file_move($filename,$array_cfg['HOME_medea']."/" .$array_cfg['path_TMP_files'],$array_cfg['HOME_medea']."/" .$array_cfg['path_IN_files']);
                        if ($OKmov)  {
                            $fullpathfilename=$array_cfg['HOME_medea']."/" .$array_cfg['path_IN_files']."/" .$filename;
                            if ($skipped_oldOK_isp == false)  {
                                $query3 = "UPDATE ".$tabla_FDdata . " SET status=" .$array_stat['SKIPPED'] . " WHERE downloaded_date < " .$fdatedown . "  AND isp_id = " .$isp_last ." AND (" .$downOKstatus . ")";
                                $res3= mysqli_query($DBcnx, $query3);
                                $nrowupd3= $res3 ? mysqli_affected_rows($DBcnx) : 0;
                                if ($res3 !== false)  {
                                    $skipped_oldOK_isp=true;
                                }
                            }
        echo "\n$query3 OK\n nrows:$nrowupd3\n\n";
                        }
                    }
                    else  {  //$HTTP200 === false
                        $fstatus=$array_stat['DOWNLOAD_ERROR'];
                    }
                  }
                  else  {
       //echo "\n\n$filelog NOT FOUND\n\n";
                  }
                }
            }
            $query = "UPDATE ".$tabla_FDdata . " SET downloaded_date=" .$fdatedown . ", retry=" .$retry . ", status=" .$fstatus . ", full_path_file='" .$fullpathfilename . "' WHERE filename='" .$filename . "' AND (" .$wherestatus . ")";
            $res2= mysqli_query($DBcnx, $query);
            $nrowupd= $res2 ? mysqli_affected_rows($DBcnx) : 0;
       //echo "$query \n nrow:$nrowupd \n\n";

        }  //end for
        mysqli_free_result($result);

    }


    // //// MAIN ////
    //VERSIONES DE PhP y LINUX
    //$version = explode('.', PHP_VERSION);
    //$nver_php=$version[0] * 10000 + $version[1] * 100 + $version[2];

    $fecha=date('d/m/Y H:i:s');
    echo "--------------------- INICIO EJECUCION $fecha ---------------- \n";

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


?>
