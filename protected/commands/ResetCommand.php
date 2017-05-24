<?php
/**
 * Created by PhpStorm.
 * User: vaishali
 * Date: 18/09/15
 * Time: 12:01
 */

class ResetCommand extends CConsoleCommand {


    /**
     * Resets a list of terminals
     * 1. Gets a list of terminals with reset_day=today.
     * 2. Adds an action in action_history, retries = 0
     * 3. Resets the forward and return volumes in the HUB trough communicator (tries 3 times)
     * 4. Resets, if necessary, sla to nominal
     * 5. Calls Archiver for terminal and for quotas
     * 6. Modifies terminal model with 0 values of upload, total, fz_upload, fz_total and gift_consumption
     *
     * @param $args
     */
    public function run($args){
        Logger::log(CLogger::LEVEL_TRACE,PHP_EOL.'Reset Process Started...', Logger::CAT_RESETTER);
        $today = date('d');

        //1.Get terminals to reset
        $terminals2reset = Terminal::model()->findAllByAttributes(array('reset_day'=>$today));

        if (!empty($terminals2reset)){
            foreach ($terminals2reset as $terminal){

                $msg = PHP_EOL. 'Registering new action' . PHP_EOL;
                Yii::log($msg, CLogger::LEVEL_TRACE, Logger::CAT_RESETTER);

                //2.add action_history, retries = 0
                $actionHistory = new ActionHistory();
                $actionHistory->isp_id = $terminal->isp_id;
                $actionHistory->sit_id = $terminal->sit_id;
                $actionHistory->action_id= 0;
                $actionHistory->action_type = ActionType::RESET_TERMINAL;
                $actionHistory->insertion_date = date('Y-m-d H:i:s');
                $actionHistory->retries = 0;

                if(!$actionHistory->save()){
                    Yii::log(PHP_EOL.'Could not save reset action in actionHistory. '.PHP_EOL, CLogger::LEVEL_ERROR, Logger::CAT_RESETTER);
                    Yii::log(var_export($actionHistory->getErrors(), true), CLogger::LEVEL_ERROR, Logger::CAT_RESETTER);
                }
                else {
                    $msg = PHP_EOL. 'Registered new action with id: '. $actionHistory->id. PHP_EOL;
                    Yii::log($msg, CLogger::LEVEL_TRACE, Logger::CAT_RESETTER);

                    //3. Try to connect and do stuff in the api, if not ok, sleep and retry.
                    $msg = PHP_EOL . 'Beginning communication with API to reset terminal: '. $terminal->terminal_id . PHP_EOL ;
                    Yii::log($msg, CLogger::LEVEL_TRACE, Logger::CAT_RESETTER);

                    do {
                        try
                        {
                            $attempt = $actionHistory->retries + 1;
                            $msg = PHP_EOL.'Try number ' . $attempt .' of communication with HUB'.PHP_EOL;
                            Yii::log($msg, CLogger::LEVEL_TRACE, Logger::CAT_RESETTER);

                            $resetForward = Communicator::resetAccumulatedForwardVolume($terminal->isp_id, $terminal->sit_id);
                            $msg = 'Reset Forward Volume for terminal '. $terminal->terminal_id . ' OK' .PHP_EOL ;
                            Yii::log($msg, CLogger::LEVEL_TRACE, Logger::CAT_RESETTER);
                            Yii::log('Hub Response resetForward: '. var_export($resetForward, true). PHP_EOL, CLogger::LEVEL_TRACE, Logger::CAT_RESETTER);

                            $resetUpload = Communicator::resetAccumulatedReturnVolume($terminal->isp_id, $terminal->sit_id);
                            $msg = 'Reset Upload Volume for terminal '. $terminal->terminal_id . ' OK' .PHP_EOL ;
                            Yii::log($msg, CLogger::LEVEL_TRACE, Logger::CAT_RESETTER);
                            Yii::log('Hub Response resetUpload: '. var_export($resetUpload, true). PHP_EOL, CLogger::LEVEL_TRACE, Logger::CAT_RESETTER);

                            $comment = 'OK';

                            $actionHistory->info_error = 'OK';

                            $msg= 'Try number '. ($actionHistory->retries) .' of communication with HUB OK. ';
                            Logger::log(CLogger::LEVEL_TRACE, $msg, Logger::CAT_RESETTER);

                            $reset = true;

                        } catch (SoapFault $e) {
                            //saves new value of retry
                            $actionHistory->retries = $attempt;
                            if(!$actionHistory->save()){
                                Yii::log(PHP_EOL.'Could not update attempt '. $attempt. 'in pending action'.PHP_EOL, CLogger::LEVEL_ERROR, Logger::CAT_RESETTER);
                                Yii::log(var_export($actionHistory->getErrors(), true), CLogger::LEVEL_ERROR, Logger::CAT_RESETTER);
                            }
                            $comment = 'NOK';
                            $msg = $comment.' '.PHP_EOL.$e->getMessage().PHP_EOL;
                            $msg.= ' Try number '. ($actionHistory->retries) .' of communication with HUB FAILED. ';
                            $msg.= PHP_EOL.$e->getTraceAsString().PHP_EOL;

                            Logger::log(CLogger::LEVEL_ERROR, $msg, Logger::CAT_RESETTER);

                            $actionHistory->info_error = $comment;
                            $actionHistory->result =PHP_EOL.$e->getMessage().PHP_EOL;

                            $reset = false;

                            sleep(60);
                            continue;
                        }
                        break;

                    } while($actionHistory->retries < Yii::app()->params['resetterRetries']);

                    //4. Terminal history; Quota history
                    Archiver::archiveTerminals(TerminalHistoryMotive::CYCLE_RESET, $terminal->terminal_id, null, null, $comment, false);
                    Archiver::archiveQuotaExtra($terminal->terminal_id);


                    //5. Update Action History
                    $actionHistory->action_completion_date = date('Y-m-d H:i:s');
                    if (!$actionHistory->save()){
                        Yii::log(PHP_EOL.'Could not move pending action with ID '. $actionHistory->action_id. 'in action history'.PHP_EOL, CLogger::LEVEL_ERROR, Logger::CAT_RESETTER);
                        Yii::log(var_export($actionHistory->getErrors(), true), CLogger::LEVEL_ERROR, Logger::CAT_RESETTER);
                    }else{
                        Yii::log('Updated action with ID '. $actionHistory->id. ' in action history'.PHP_EOL, CLogger::LEVEL_TRACE, Logger::CAT_RESETTER);
                    }


                    //6. If reset=true, update terminal.
                    if ($reset){
                        $terminal->fz_upload_gap = 0;
                        $terminal->fz_total_gap = 0;
                        $terminal->gift_consumption = 0;

                        if (!$terminal->save()){
                            Yii::log(PHP_EOL.'Could not update terminal with ID '. $terminal->terminal_id.PHP_EOL, CLogger::LEVEL_ERROR, Logger::CAT_RESETTER);
                            Yii::log(var_export($terminal->getErrors(), true), CLogger::LEVEL_ERROR, Logger::CAT_RESETTER);
                        }
                        Logger::log(CLogger::LEVEL_TRACE, PHP_EOL.'Reset terminal with ID '. $terminal->terminal_id. ' completed.'.PHP_EOL, Logger::CAT_RESETTER);
                    }
                }
            }
        }
        else{
            Logger::log(CLogger::LEVEL_INFO, 'There are no terminals that need to be reset today. Today is '.$today, Logger::CAT_RESETTER);
        }
    }

    /**
     * @return string
     */
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