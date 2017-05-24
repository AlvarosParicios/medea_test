<?php

/**
 * Created by PhpStorm.
 * User: jtriana
 * Date: 10/08/2015
 * Time: 11:12 AM
 */
class ExecuteCommand extends CConsoleCommand
{

    //if changes are made here, update initGlobals funcion
    private $CHANGE_SLA = "/../yiic execute changeSLA";
    private $NOTIFY = "/../yiic execute notify";

    private $displayOutput = false;

    public function actionIndex($verbose = false){

        Logger::log(CLogger::LEVEL_INFO, "Starting Executor...", Logger::CAT_EXECUTOR);

        $this->initGlobals();
        $this->displayOutput = $verbose;
        $actions = ActionPending::model()->findAll();
        Logger::log(CLogger::LEVEL_INFO, "Preparing to process " . count($actions) . " action pendings...", Logger::CAT_EXECUTOR);

        foreach($actions as $action){
            switch ($action->action_type){
                case ActionType::CHANGE_SLA:
                    $this->execute($this->CHANGE_SLA, $action->params, $action->action_id);
                    break;
                case ActionType::NOTIFY:
                    $this->execute($this->NOTIFY, null, $action->action_id);
                    break;
                case ActionType::RESET_TERMINAL:
                    //todo: unused, but listed for possible future uses
                    break;
            }
            sleep(Yii::app()->params['executorWaitSeconds']);
        }
    }

    public function actionChangeSLA($sit_id, $isp_id, $sla, $action_id){

        Logger::log(CLogger::LEVEL_INFO, "Performing SLA Change for terminal sit: " . $sit_id . " isp: " . $isp_id . " sla id: " . $sla, Logger::CAT_EXECUTOR);

        $error = false;
        $result = null;
        try{
            Logger::log(CLogger::LEVEL_INFO, "Calling Communicator... ", Logger::CAT_EXECUTOR);
            $result = Communicator::changeSLA($isp_id, $sit_id, $sla);
            Logger::log(CLogger::LEVEL_INFO, "Communicator response: " . var_export($result, true), Logger::CAT_EXECUTOR);
        }catch (Exception $e) {
            $result = $e->getMessage();
            $error = true;
            Logger::log(CLogger::LEVEL_ERROR, "Exception in Communicator: " . var_export($result, true), Logger::CAT_EXECUTOR);
        }
        $this->archive($action_id, $result, $error);
    }

    public function actionNotify($action_id){

        $pendingNotification = ActionPending::model()->findByPk($action_id);

        $requestData = json_decode($pendingNotification->params, true);
        $requestData['login'] = Yii::app()->params['notificationAuth'];
        $requestData = json_encode($requestData);
        $url = Yii::app()->params['notificationUrl'];

        Logger::log(CLogger::LEVEL_INFO,
            "Sending notification to CRM, for terminal with sit: " . $pendingNotification->sit_id . " and isp: " . $pendingNotification->isp_id .
            " To url: " . $url .
            " Data sent: ". $requestData,
            Logger::CAT_EXECUTOR, false);

        $response = CRMCommunicator::CallRestAPI($url, $requestData);
        $error = !$response;

        Logger::log(CLogger::LEVEL_INFO, "CRMCommunicator response: " . var_export($response, true), Logger::CAT_EXECUTOR, false);
        $this->archive($action_id, $response, $error);
    }

    private function execute($command, $params, $action_id){
        //build call parameters
        $parameters = ' ';

        if (!empty($params)){
            $params = json_decode($params);
            foreach($params as $key => $value){
                $parameters .= '--' . $key . '=' . $value . ' ';
            }
        }

        $parameters .= '--action_id=' . $action_id;

        Logger::log(CLogger::LEVEL_INFO, "Preparing to Execute: " . 'bash -c "exec nohup setsid ' . $command . $parameters .' &"', Logger::CAT_EXECUTOR);
        //exec('bash -c "exec nohup setsid ' . $command . $parameters . ' > /dev/null 2>&1 &"');
        exec('bash -c "exec nohup setsid ' . $command . $parameters . ' &"', $output);
	    Logger::log(CLogger::LEVEL_INFO, var_export($output, true), Logger::CAT_EXECUTOR, $this->displayOutput);
    }

    private function archive($action_id, $result = null, $error = false){

        Logger::log(CLogger::LEVEL_INFO, "Calling Executor archive funtion for action: " . $action_id, Logger::CAT_EXECUTOR);
        $action = ActionPending::model()->findByPk($action_id);

        if (!$error || $action->retries >= Yii::app()->params['executorRetries']){
            $actionHistory = new ActionHistory();
            $actionHistory->attributes = $action->attributes;
            $actionHistory->action_completion_date = date('Y-m-d H:i:s');
            $actionHistory->result = json_encode($result);
            $actionHistory->info_error = ($action->retries >= Yii::app()->params['executorRetries']) ? "NOK" : "OK";
            if ($actionHistory->save()){
                $action->delete();
                Logger::log(CLogger::LEVEL_INFO, "Action archived successfully. Id: " . $actionHistory->action_id, Logger::CAT_EXECUTOR);
                return true;
            }else{
                Logger::log(CLogger::LEVEL_ERROR, "Error archiving action. Id: " . $action->action_id, Logger::CAT_EXECUTOR);
                Logger::log(CLogger::LEVEL_ERROR, "Errors: " . var_dump($actionHistory->getErrors(), true), Logger::CAT_EXECUTOR);
            }
        }else{
            Logger::log(CLogger::LEVEL_ERROR, "Registering error retry for action: " . $action->action_id, Logger::CAT_EXECUTOR);
            $action->retries++;
            $action->save();
            return false;
        }
    }

    private function initGlobals(){
        $this->CHANGE_SLA = __DIR__ . $this->CHANGE_SLA;
        $this->NOTIFY = __DIR__ . $this->NOTIFY;
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