<?php

/**
 * Created by PhpStorm.
 * User: Vaishali
 * Date: 07/10/2015
 * Time: 03:17 PM
 */
class Archiver
{

    public static $verbose_mode = false;

    public static function archiveTerminals($motive = TerminalHistoryMotive::HOURLY_BACKUP,  $terminal_medea_id = null, $sit_id = null, $isp_id = null, $comment = null, $commentAll = false){

        Logger::log(CLogger::LEVEL_INFO, "Starting Archiver Process for Terminals...", Logger::CAT_ARCHIVER);

        if (isset($terminal_medea_id)) {
            Logger::log(CLogger::LEVEL_INFO, "Searching terminal by Medea id: " . $terminal_medea_id, Logger::CAT_ARCHIVER, self::$verbose_mode);
            $terminal = Terminal::model()->findByPk($terminal_medea_id);
        }
        else if (isset($sit_id) && isset($isp_id)) {
            Logger::log(CLogger::LEVEL_INFO, "Searching terminal by sit id: ". $sit_id . ", isp id: " . $isp_id, Logger::CAT_ARCHIVER, self::$verbose_mode);
            $terminal = Terminal::model()->findByAttributes(array('sit_id' => $sit_id, 'isp_id' => $isp_id));
        }
        else {
            //Execute insert all query directly due to efficiency
            Logger::log(CLogger::LEVEL_INFO, "Trying to insert all terminals in terminal_history", Logger::CAT_ARCHIVER, self::$verbose_mode);

            try{
                $connection=Yii::app()->db;

                //Primero obtenemos el numero de terminales que insertaremos en el historico
                $count_sql = "SELECT count(*) as cont FROM `terminal`";

                $command=$connection->createCommand($count_sql);
                $data = $command->queryRow();
                $cont_terminales = $data['cont'];

                $var_sql ="INSERT INTO terminal_history
                            (sit_id, isp_id, bss_plan, reset_day, upload_data_effective, upload_data_real, total_data_effective, total_data_real,
                            upload_data_real_residual, total_data_real_residual, upload_data_effective_residual, total_data_effective_residual,
                            insertion_date, motive, gift_consumption, sla_status_id, fz_upload_gap, fz_total_gap, last_update_date)
                            SELECT sit_id, isp_id, bss_plan, reset_day, upload_data_effective, upload_data_real, total_data_effective, total_data_real, 0, 0, 0, 0, now(),
                            ". TerminalHistoryMotive::HOURLY_BACKUP.", gift_consumption, sla_status_id, fz_upload_gap, fz_total_gap, last_update_date from terminal";

                $command=$connection->createCommand($var_sql);
                $command->query();

            }
            catch(Exception $e){
                Logger::log(CLogger::LEVEL_ERROR, "Error en BD: ".var_export($e->getMessage()), Logger::CAT_ARCHIVER, self::$verbose_mode);
            }
            Logger::log(CLogger::LEVEL_INFO, "INSERTED OK ".$cont_terminales." terminals in terminal_history", Logger::CAT_ARCHIVER, self::$verbose_mode);

            return;
        }

        if ($terminal){
            Logger::log(CLogger::LEVEL_INFO, "Updating info for the terminal sit id: " . $sit_id . ", isp id: " . $isp_id, Logger::CAT_ARCHIVER, self::$verbose_mode);

            $terminalHistory = new TerminalHistory();
            $terminalHistory->attributes = $terminal->attributes;
            $terminalHistory->motive = $motive;
            $terminalHistory->insertion_date = date('Y-m-d H:i:s');

            $terminalHistory->upload_data_real_residual = 0;
            $terminalHistory->total_data_real_residual = 0;
            $terminalHistory->upload_data_effective_residual = 0;
            $terminalHistory->total_data_effective_residual = 0;

            if (!empty($comment) && (count($terminal) == 1 || $commentAll)){
                $terminalHistory->comment = $comment;
            }

            if ($terminalHistory->save()){
                Logger::log(CLogger::LEVEL_INFO, "Terminal info: " . var_export($terminalHistory->attributes, true), Logger::CAT_ARCHIVER, self::$verbose_mode);
            }else{
                Logger::log(CLogger::LEVEL_ERROR, "Error saving history entry for terminal sit id: ". $sit_id . ", isp id: " . $isp_id, Logger::CAT_ARCHIVER, self::$verbose_mode);
                Logger::log(CLogger::LEVEL_ERROR, "Error: " . var_export($terminalHistory->getErrors(), true), Logger::CAT_ARCHIVER, self::$verbose_mode);

            }
        }else{
            Logger::log(CLogger::LEVEL_ERROR, "No terminal found.", Logger::CAT_ARCHIVER, self::$verbose_mode);
        }
    }

    public static function archiveQuotaExtra($terminal_medea_id = null){

        Logger::log(CLogger::LEVEL_INFO, "Starting Archiver Process for Extra Quotas...", Logger::CAT_ARCHIVER);

        $extras = array();
        $criteria = new CDbCriteria();
        $criteria->addCondition('completion_date IS NOT NULL', 'OR');
        $criteria->addCondition('expiration_date <= NOW()', 'OR');

        if (isset($terminal_medea_id)){
            Logger::log(CLogger::LEVEL_INFO, "Searching for extra quotas for terminal id: ". $terminal_medea_id, Logger::CAT_ARCHIVER, self::$verbose_mode);
            $extras = QuotaExtra::model()->findAllByAttributes(array('terminal' => $terminal_medea_id), $criteria);

        }else{
            Logger::log(CLogger::LEVEL_INFO, "Searching for ALL extra quotas", Logger::CAT_ARCHIVER, self::$verbose_mode);
            $extras = QuotaExtra::model()->findAll($criteria);
        }
        if (!empty($extras)){
            foreach ($extras as $extra){
                $extraHistory = new QuotaExtraHistory();
                $extraHistory->attributes = $extra->attributes;
                if (strtotime($extraHistory->expiration_date) <= time()){
                    $extraHistory->status = QuotaExtraStatus::EXPIRED;
                }
                $extraHistory->insertion_date = date('Y-m-d H:i:s');
                if ($extraHistory->save()){
                    $extra->delete();
                    Logger::log(CLogger::LEVEL_INFO, "Quota extra info: " . var_export($extraHistory->attributes, true), Logger::CAT_ARCHIVER, self::$verbose_mode);
                }else{
                    Logger::log(CLogger::LEVEL_ERROR, "Error saving history entry for quota extra with id: ". $extra->extra_id, Logger::CAT_ARCHIVER, self::$verbose_mode);
                }
            }
        }else{
            Logger::log(CLogger::LEVEL_ERROR, "No extra quotas for terminal id: ". $terminal_medea_id, Logger::CAT_ARCHIVER, self::$verbose_mode);
        }
    }

}