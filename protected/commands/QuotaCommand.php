<?php

/**
 * Created by PhpStorm.
 * User: jtriana
 * Date: 20/08/2015
 * Time: 11:12 AM
 */
class QuotaCommand extends CConsoleCommand
{
    //indexes in csv order
    const ACC_UPLOAD = 7;
    const ACC_DOWNLOAD = 6;
    const PART_UPLOAD = 3;
    const PART_DOWNLOAD = 2;
    const HIGHP_UPLOAD = 9;
    const HIGHP_DOWNLOAD = 8;
    const REALT_UPLOAD = 13;
    const REALT_DOWNLOAD = 12;
    const TOTAL_UPLOAD = 17;
    const TOTAL_DOWNLOAD = 16;

    //freezone flags
    private $fzActive = array();

    //verbose
    public $verbose_mode = false;

    //queue
    private $filesQueue;

    //internal counter
    private $filesCount = 0;

    public function run($args){

        //inicializar la cola
        $this->filesQueue = new CQueue();

        //verbose mode
        foreach($args as $arg){
            if (strpos($arg, "--verbose_mode") !== false || strpos($arg, "-v") !== false){
                $value = substr($arg, strpos($arg, "=") + 1);
                if ($value === 1 || $value === "1" || $value === true || $value === "true"){
                    $this->verbose_mode = true;
                }
            }
        }

        Logger::log(CLogger::LEVEL_INFO, "Medea Core Process Started");


            //esto es independiente del tiempo que duerma quota
        while(true){

            try{

                $newFilesCount = FileDownloaderData::model()->count();
                if ($newFilesCount != $this->filesCount) {

                    //database access to get the files to process
                    $filesToProcess = FileDownloaderData::model()->findAllByAttributes(
                        array(
                            'status' => FileDownloaderStatus::DOWNLOAD_OK,
                            'processing_date' => null,
                        ));

                    Logger::log(CLogger::LEVEL_INFO, "Found " . count($filesToProcess) . " NEW files");

                    foreach ($filesToProcess as $file) {
                        $this->filesQueue->enqueue($file);
                        $file->status = FileDownloaderStatus::QUEUED;
                        $file->setIsNewRecord(false);
                        $file->save();
                    }
                    $this->filesCount = $newFilesCount;
                }

                //Processing files in Queue

                if($this->filesQueue->count() > 0){

                    $fileToProcess = $this->filesQueue->dequeue();
                    Logger::log(CLogger::LEVEL_INFO, "Processing file: ". $fileToProcess->full_path_file . ". From ISP: " . $fileToProcess->isp_id);

                    //process file
                    $processingErrors = $this->updateQuota(file_get_contents($fileToProcess->full_path_file), $fileToProcess->isp_id);
                    Logger::log(CLogger::LEVEL_INFO, "Consumptions updated.");

                    $fileToProcess->status = FileDownloaderStatus::PROCESSED_OK;
                    $fileToProcess->processing_date = $this->getActualTimestamp();

                    if (!$fileToProcess->save()){
                        Logger::log(CLogger::LEVEL_ERROR, "Error updating processing date. Filename: " . $fileToProcess->full_path_file, Logger::CAT_QUOTA, $this->verbose_mode);
                        Logger::log(CLogger::LEVEL_ERROR, var_export($fileToProcess->getErrors(), true), Logger::CAT_QUOTA, $this->verbose_mode);
                    }

                    Logger::log(CLogger::LEVEL_INFO, "Starting quota check...");
                    $toSkip = empty($processingErrors) ? array() : array_keys($processingErrors);
                    $this->checkQuota($fileToProcess->isp_id, $toSkip);
                    Logger::log(CLogger::LEVEL_INFO, "Quota checking completed.");
                    Logger::log(CLogger::LEVEL_INFO, "Remaining files in queue: " . $this->filesQueue->count());

                    $sleepTime = 0;
                }else{
                    $sleepTime = 30;
                }
                sleep($sleepTime);

            }catch (Exception $e){
                Logger::log(CLogger::LEVEL_ERROR, $e->getTraceAsString());
            }
        }
    }


    /*
     *
     * Esta función procesa los ficheros detectados en la tabla file downloader data
     *
     * @return boolean, returns true if the file was successfully processed, false otherwise.
     * */

    private function updateQuota($fileContent, $isp_id){

        $errors = array();
        $lines = explode(PHP_EOL, trim($fileContent));
        //data es las filas del csv que tienen los datos de los terminales.
        $data = array_slice($lines, 4);

        foreach($data as $terminalData){

            //se obtiene el terminal a actualizar y sus datos
            $terminalData = explode(',', $terminalData);
            $terminal = Terminal::model()->findByAttributes(array('sit_id' => $terminalData[0], 'isp_id' => $isp_id));

            //si no existe el terminal continuamos
            if (empty($terminal)){
                if ($this->verbose_mode){
                    Logger::log(CLogger::LEVEL_INFO, 'Terminal: ' . $terminalData[0] . ' ISP: ' . $isp_id . ' not found.', Logger::CAT_QUOTA, $this->verbose_mode);
                }
                continue;
            }

            //si el terminal no tiene consumo se ignora para evitar UPDATES innecesarios en BD
            if ($terminalData[self::TOTAL_UPLOAD] == 0 && $terminalData[self::TOTAL_DOWNLOAD] == 0 &&
                $terminal->upload_data_real == $this->getAccumulatedUpload($terminalData) &&
                $terminal->total_data_real == $this->getAccumulatedTotal($terminalData)){
                if ($this->verbose_mode){
                    Logger::log(CLogger::LEVEL_INFO, 'Terminal: ' . $terminalData[0] . ' ISP: ' . $isp_id . ' has no consumption. Ignoring update.', Logger::CAT_QUOTA, $this->verbose_mode);
                    $errors[$terminal->terminal_id] = "Ignored, no consumption";
                }
                continue;
            }

            //si no está definida la bandera de freezone para un terminal la inicializamos a false
            if (!isset($this->fzActive[$terminal->terminal_id])){
                $this->fzActive[$terminal->terminal_id] = false;
            }

            //si freezone existe y está activa no se contabiliza el consumo efectivo y se activa la bandera fz activa
            // para luego contabilizar la diferencia (gap)
            $freeZone = isset($terminal->bssPlan->fz0) ? $terminal->bssPlan->fz0 : null;
            if (empty($freeZone) || !$freeZone->isFreeZoneActive()){
                //aquí entra solo cuando la freezone se acaba de desactivar
                //quita la bandera y actualiza el gap para calcular el consumo efectivo
                //si el terminal tiene un plan sin freezone nunca entra por aquí
                if (!empty($freeZone) && $this->fzActive[$terminal->terminal_id]){
                    //el gap es el total actual - (el consumo efectivo al entrar en el freezone + los últimos 5 minutos que ha consumido desde que salió del freezone)
                    $terminal->fz_upload_gap = $this->getAccumulatedUpload($terminalData) - ($terminal->upload_data_effective + $this->getPartialUpload($terminalData));
                    $terminal->fz_total_gap = $this->getAccumulatedTotal($terminalData) - ($terminal->total_data_effective + $this->getPartialTotal($terminalData));

                    //si se resetea el terminal en freezone, el gap es lo que se ha consumido desde el reseteo, para que el consumo efectivo
                    //sea cero al salir de freezone
                    if ($terminal->fz_upload_gap < 0 || $terminal->fz_total_gap < 0){
                        $terminal->fz_upload_gap = $this->getAccumulatedUpload($terminalData);
                        $terminal->fz_total_gap = $this->getAccumulatedTotal($terminalData);
                    }

                    $this->fzActive[$terminal->terminal_id] = false;
                }

                //aquí se actualiza el consumo efectivo, que no es más que el consumo real - el gap (cantidad consumida mientras el freezone estuvo activo)
                //el gap se calcula restando el acumulado real - la cantidad efectiva al inicio del freezone justo cuando se desactiva el fz
                //al resetear el terminal se ponen a cero los gaps
                $terminal->upload_data_effective = $this->getAccumulatedUpload($terminalData) - $terminal->fz_upload_gap;
                $terminal->total_data_effective = $this->getAccumulatedTotal($terminalData) - $terminal->fz_total_gap;

                //este caso se produce cuando hay un reset dentro de FZ y es muy próximo al final de esta. El reseteo normalmente tarda 2 o 3 ficheros en llegar a
                //MEDEA, con lo cual si se resetea justo antes de salir de FZ se actualizarán los gaps de manera correcta pero en el siguiente archivo
                //llegaran los consumos acumulados mucho menores que los gaps, lo cual producirá consumos efectivos negativos.
                // si detectamos esto:
                if ($terminal->upload_data_effective < 0 || $terminal->total_data_effective < 0){
                    //hacemos el consumo efectivo = consumo total
                    $terminal->upload_data_effective = $terminal->upload_data_real;
                    $terminal->total_data_effective = $terminal->total_data_real;
                    //limpiamos los gaps
                    $terminal->fz_upload_gap = 0;
                    $terminal->fz_total_gap = 0;
                }

                //aquí se actualiza el consumo de la cuota extra si existe, en caso de exceso de upload y de total
                $extra = $terminal->getExtraQuota();
                if ($extra && $extra->isActivated() && !$extra->isCompleted()){
                    if ($terminal->isTotalQuotaExceeded()){

                        //esto solo se ejecuta en caso de que una cuota adicional estuviese registrando tráfico de subida y el consumidor se pasa de cuota total.
                        //Habría que cambiar el punto de inicio de contabilización de la cuota para registrar los cambios.
                        if ($extra->traffic_type == QuotaExtraTrafficType::UPLOAD_TRAFFIC){
                            $extra->traffic_type = QuotaExtraTrafficType::TOTAL_TRAFFIC;
                            $extra->initial_consumption = $terminal->total_data_effective - ($terminal->getTotalQuotaExceeded());
                        }

                        //intital consumption se inicializa en el momento en que se activa la cuota adicional
                        $extra->consumed_quota = $terminal->total_data_effective - $extra->initial_consumption;
                        Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $terminal->sit_id . " ISP: " . $terminal->isp_id . " has exceeded its TOTAL quota. Using EXTRA quota.", Logger::CAT_QUOTA, $this->verbose_mode);

                    }else if ($terminal->isUploadQuotaExceeded()){

                        $extra->consumed_quota = $terminal->upload_data_effective - $extra->initial_consumption;
                        Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $terminal->sit_id . " ISP: " . $terminal->isp_id . " has exceeded its UPLOAD quota. Using EXTRA quota.", Logger::CAT_QUOTA, $this->verbose_mode);
                    }
                    $extra->save();
                }


            //en el momento en que se activa el freezone se pone la bandera
            }elseif($freeZone->isFreeZoneActive() && !$this->fzActive[$terminal->terminal_id]){
                $this->fzActive[$terminal->terminal_id] = true;
            }

            //actualización de los datos reales, esto es continuo
            $terminal->upload_data_real = $this->getAccumulatedUpload($terminalData);
            $terminal->total_data_real = $this->getAccumulatedTotal($terminalData);
            $terminal->last_update_date = $this->getActualTimestamp();

            //guardar los consumos actualizados en BBDD y loggear errores
            if (!$terminal->save()){
                Logger::log(CLogger::LEVEL_ERROR, 'Terminal: ' . $terminal->sit_id . ' ISP: ' . $terminal->isp_id . ' FAILED UPDATE. Errors: ', Logger::CAT_QUOTA, $this->verbose_mode);
                Logger::log(CLogger::LEVEL_ERROR, var_export($terminal->getErrors(), true), Logger::CAT_QUOTA, $this->verbose_mode);
                $errors[$terminal->id] = $terminal->getErrors();
            }else{
                Logger::log(CLogger::LEVEL_INFO, 'Terminal: ' . $terminal->sit_id . ' ISP: ' . $terminal->isp_id . ' UPDATED.', Logger::CAT_QUOTA, $this->verbose_mode);
            }
        }
        Logger::log(CLogger::LEVEL_INFO, 'Updated ' . count($data) . ' terminal consumptions. Errors: ' . count($errors));
        if (count($errors) > 0){
            Logger::log(CLogger::LEVEL_ERROR, var_export($errors, true), Logger::CAT_QUOTA, $this->verbose_mode);
        }
        return $errors;
    }


    public function checkQuota($isp_id, $skippedTerminals){

        $criteria = new CDbCriteria();
        $criteria->addInCondition('isp_id', array($isp_id));
        $terminals = Terminal::model()->findAll($criteria);

        foreach($terminals as $terminal){
            if (in_array($terminal->terminal_id, $skippedTerminals)){
                //Logger::log(CLogger::LEVEL_INFO, 'Terminal: ' . $terminal->sit_id . ' ISP: ' . $terminal->isp_id . ' OMITTED.', Logger::CAT_QUOTA, $this->verbose_mode);
                continue;
            }
            $terminal->checkQuota($this->verbose_mode);
        }
    }

    private function getActualTimestamp(){
        return date('Y-m-d H:i:s');
    }

    private function getAccumulatedUpload($terminalData){
        return $terminalData[self::ACC_UPLOAD];
    }

    private function getAccumulatedTotal($terminalData){
        return $terminalData[self::ACC_UPLOAD] + $terminalData[self::ACC_DOWNLOAD];
    }

    private function getPartialUpload($terminalData){
        return $terminalData[self::PART_UPLOAD] + $terminalData[self::HIGHP_UPLOAD] +
        $terminalData[self::REALT_UPLOAD];
    }

    private function getPartialTotal($terminalData){
        return $terminalData[self::PART_UPLOAD] + $terminalData[self::PART_DOWNLOAD] +
        $terminalData[self::HIGHP_UPLOAD] + $terminalData[self::HIGHP_DOWNLOAD] +
        $terminalData[self::REALT_UPLOAD] + $terminalData[self::REALT_DOWNLOAD];
    }


    public function getHelp()
    {
        $help='Usage: '.$this->getCommandRunner()->getScriptName().' '.$this->getName();
        $options=$this->getOptionHelp();
        if(empty($options))
            return $help."\n";
        if(count($options)===1)
            return $help.' '.$options[0]."\n";
        $help.=" <action>\nActions:\n";
        foreach($options as $option)
            $help.='    '.$option."\n";
        return $help;
    }

}