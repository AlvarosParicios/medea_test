<?php
/**
 * Created by PhpStorm.
 * User: dalvaro
 * Date: 21/09/15
 * Time: 10:16 AM
 */

class ApiCrmController extends GxController{

    // Members
    /**
     * Key which has to be in HTTP USERNAME and PASSWORD headers
     */
    const APPLICATION_ID = 'ASCCPE';

    //ERROR CODES FOR PLAN API
    const MISSING_PARAMETERS_ERROR = 1000;
    const QUOTA_PROFILE_ERROR = 1001;
    const FREE_ZONE_ERROR = 1002;
    const BSS_PLAN_ERROR = 1003;

    /**
     * Default response format
     * either 'json' or 'xml'
     */
    private $format = 'json';

    //private $user_front;
    private $api_name;

    public function filters() {
        return array(
            //'accessControl',
            'postOnly + createTerminal, decommissionTerminal,decommissionHardTerminal,createQuotaExtra,deleteQuotaExtra,updateTerminal,updateTerminalPlan,viewTerminal',
            'authCheck - index, error'

        );
    }

    public function filterAuthCheck($filterChain){
        //Comprobamos el Usuario y la contraseña
        $error = $this->checkUserPass(@$_POST['user_api'],@$_POST['pass_api']);
        if (!empty($error)){
            $this->sendError($error);
        }
        $filterChain->run();
    }


    public function accessRules() {
        return array(
            array('allow',
                'roles'=>array('Admin'),
            ),
            array('deny',
                'users'=>array('*'),
            ),
        );
    }

    public function actionError()
    {
        if($error=Yii::app()->errorHandler->error)
        {
            if(Yii::app()->request->isAjaxRequest)
                echo $error['message'];
            else
                $this->render('error', $error);
        }
    }

    public function actionIndex(){
        $this->getAPI();
    }

    private function getAPI(){
        echo "<pre>";
        echo PHP_EOL . "Available methods: " . PHP_EOL . PHP_EOL;

        $methods = get_class_methods(get_class($this));
        $apiList = array();

        foreach($methods as $method) {
            if (preg_match('/^action+\w{2,}/',$method)) {
                if (strpos($method, 'Index')){
                    continue;
                }
                $apiList[] = str_ireplace("action", "", $method);
            }
        }

        print_r($apiList);
        echo "</pre>";
    }

    /**
     * Get Bss plans (quota profiles) in Medea
     * 1. Prepare BssPlan model
     * 2. Get data from model (all register).
     *
     */

    public function actionGetBssPlan()
    {
        Yii::log('LLAMADA API GET BSS PLAN',CLogger::LEVEL_TRACE,"api");
        $this->api_name="getBssPlan";


        //Creamos el modelo BssPlan

        $bss_planes = BssPlan::model()->findAll();
        /*
        echo "<pre>";
        print_r($model);
        echo "</pre>";
        exit();
        */

        $arr_bss_planes = array();

        foreach ($bss_planes as $item){
            $arr_bss_planes[]= $item->getAttributes();
        }

        Yii::log('GET '.count($arr_bss_planes).'  Bss planes ',CLogger::LEVEL_TRACE,"api");
        $this->sendOK($arr_bss_planes,false);

    }


    public function actionModifyFusService(){

        try{
            //If upload_quota or upload_exceded_sla are NULL or =="" we must convert in zero

            // Variable para saber si tenemos que borrar el freezone
            $delete_freezone = false;

            if(is_null($_POST['upload_quota']) || $_POST['upload_quota']==""){
                Logger::log(CLogger::LEVEL_INFO, "Convet upload_quota to zero", Logger::CAT_API, false);
                $_POST['upload_quota']=0;
            }

            if(is_null($_POST['upload_exceded_sla'])  || $_POST['upload_exceded_sla']==""){
                Logger::log(CLogger::LEVEL_INFO, "Convet upload_exceded_sla to zero", Logger::CAT_API, false);
                $_POST['upload_exceded_sla']=0;
            }

            //mandatory params
            if (!isset($_POST['action'])){
                throw new Exception("Must specify the action to perform. Options are: 'create' and 'update'");
            }

            if ($_POST['action'] == 'create'){
                $this->api_name="createFusService";
                if (!isset(
                    $_POST['fus_service_id'],
                    $_POST['total_quota'],
                    $_POST['upload_quota'],
                    $_POST['nominal_sla'],
                    $_POST['upload_exceded_sla'],
                    $_POST['total_exceded_sla'])){
                    throw new Exception("Missing Parameterssssssss. Parameters fus_service_id, total_quota, upload_quota, nominal_sla, upload_exceded_sla, total_exceded_sla are mandatory on creation.");
                }
                $createNewService = true;

            }else if ($_POST['action'] == 'update'){
                $this->api_name="modifyFusService";
                if (!isset($_POST['fus_service_id'])){
                    throw new Exception("Missing Parameters. Parameter fus_service_id is mandatory on update.");
                }
                $delete_freezone = $_POST['delete_freezone'];
                $createNewService = false;
            }else{
                throw new Exception("Must specify the action to perform. Options are: 'create' and 'update'");
            }

            //optional parameters
            $empty_params = 0;
            $params = array('fus_service_description' , 'total_quota' , 'upload_quota' , 'nominal_sla' , 'upload_exceded_sla' , 'total_exceded_sla' , 'free_zone_init_min' , 'free_zone_end_min' , 'free_zone_init_hour' , 'free_zone_end_hour' , 'new_fus_service_id');
            foreach($params as $param){
                if (!isset($_POST[$param]) || strlen($_POST[$param]) == 0){
                    $_POST[$param] = null;
                    $empty_params++;
                }
            }

            if ($empty_params == count($params)){
                throw new Exception("Must specify at least one param to update.");
            }

            Logger::log(CLogger::LEVEL_INFO, "Modify Fus Service, Parameters received: " . var_export($_POST, true), Logger::CAT_API, false);

            $response = $this->saveFusService($createNewService,
                $_POST['fus_service_id'],
                $_POST['fus_service_description'],
                $_POST['total_quota'],
                $_POST['upload_quota'],
                $_POST['nominal_sla'],
                $_POST['upload_exceded_sla'],
                $_POST['total_exceded_sla'],
                $_POST['free_zone_init_min'],
                $_POST['free_zone_end_min'],
                $_POST['free_zone_init_hour'],
                $_POST['free_zone_end_hour'],
                $delete_freezone);

                Logger::log(CLogger::LEVEL_INFO, $_POST['action'] . " fus service plan: " . json_encode($response), Logger::CAT_API, false);
                $this->sendOk($response, false);

        }catch (Exception $e){
            //Logger::log(CLogger::LEVEL_ERROR, $e->getTraceAsString(), Logger::CAT_API, false);
            if (strpos($e->getMessage(), 'servicio_fus_id_index') !== false) {
                $error_message = "QUOTA_PROFILE_ALREADY_EXISTS";
                $this->sendError($error_message);
            }
            else{
                //$this->sendError($e->getMessage());
                $this->sendError("DATABASE_ERROR");
            }

        }
    }

    public function actionGetFusServices(){

        Logger::log(CLogger::LEVEL_INFO, "Get Fus Services, Parameters received: " . var_export($_POST, true), Logger::CAT_API, false);
        $this->api_name="getFusServices";

        $plans = array();
        $response = null;

        if (isset($_POST['fus_service_id']) && strlen($_POST['fus_service_id']) > 0){
            $plan = BssPlan::model()->findByAttributes(array("fus_service_id" => $_POST['fus_service_id']));
            if (!empty($plan)) $plans[] = $plan;
        }else{
            $plans = BssPlan::model()->findAll();
        }

        if (!empty($plans)){
            foreach($plans as $plan){
                if (!empty($plan->fz)){
                    Yii::log('Datos fz: '.$plan->fz0, CLogger::LEVEL_INFO,"api");
                    $fz_initial = date('H:i',strtotime($plan->fz0->initial_hour.":".$plan->fz0->initial_min));
                    $fz_final = date('H:i',strtotime($plan->fz0->final_hour.":".$plan->fz0->final_min));
                    $fz_total = $fz_initial."--".$fz_final;
                    //$fz_total = $plan->fz0->initial_hour.":".$plan->fz0->initial_min."--".$plan->fz0->final_hour.":".$plan->fz0->final_min;
                    Yii::log('Datos plan: '.$fz_total, CLogger::LEVEL_INFO,"api");
                }

                $response[] = empty($plan->fz) ? array_merge($plan->attributes, $plan->quotaProfile->attributes) : array_merge($plan->attributes, $plan->quotaProfile->attributes, $plan->fz0->attributes, array("fz_total"=>$fz_total));
                //$response[] = empty($plan->fz) ? array_merge($plan->attributes, $plan->quotaProfile->attributes) : array_merge($plan->attributes, $plan->quotaProfile->attributes, array("fz_total"=>$fz_total));
                Yii::log('Datos totales: '.var_export($response, true), CLogger::LEVEL_INFO,"api");

            }
            Logger::log(CLogger::LEVEL_INFO, "Get Fus Services, Parameters response: " . var_export($response, true), Logger::CAT_API, false);
            $this->sendOk($response, false);
        }else{
            $response = "No FUS Service found matching the search criteria.";
            $this->sendError($response);
        }

        Logger::log(CLogger::LEVEL_INFO, "Response: " . json_encode($response), Logger::CAT_API, false);
    }

    private function saveFusService($isNewFusService = true, $fus_service_id, $description = null,
                                    $total_quota = null, $upload_quota = null, $sla_nominal = null, $sla_upload_exceded = null,
                                    $sla_total_exceded = null, $initial_min = null, $final_min = null,
                                    $initial_hour = null, $final_hour = null, $delete_freezone = false){

        $entities = array();
        Logger::log(CLogger::LEVEL_INFO, " delete_freezone =  " . $delete_freezone, Logger::CAT_API, false);
        if ($isNewFusService){
            if (!isset($fus_service_id, $total_quota, $upload_quota, $sla_nominal, $sla_upload_exceded, $sla_total_exceded)){
                $response = "Missing Parameters. Parameters fus_service_id, total_quota, upload_quota, nominal_sla, upload_exceded_sla, total_exceded_sla are mandatory on creation.";
                Logger::log(CLogger::LEVEL_ERROR, $response, Logger::CAT_API, false);
                throw new Exception($response, self::MISSING_PARAMETERS_ERROR);
            }
            $entities['bssPlan'] = new BssPlan();
            $entities['quotaProfile'] = new QuotaProfile();

        }else{
            $entities['bssPlan'] = BssPlan::model()->findByAttributes(array("fus_service_id" => $fus_service_id));

            if (empty($entities['bssPlan'])){
                throw new Exception("Entered FUS Service ID doesn't exist.");
            }

            $entities['quotaProfile'] = $entities['bssPlan']->quotaProfile;
            $entities['freeZone'] = $entities['bssPlan']->fz0;
        }

        if (isset($initial_min, $final_min, $initial_hour, $final_hour) && ($isNewFusService || empty($entities['freeZone']))){
            $entities['freeZone'] = new FreeZone();
        }else if (empty($entities['freeZone'])){
            $entities['freeZone'] = null;
        }

        //echo "<pre>";

        $transaction = Yii::app()->db->beginTransaction();
        try{

            //massive assignment
            foreach($entities as $entity){
                if (!empty($entity)){
                    $properties = $entity->attributeNames();
                    foreach($properties as $property){
                        if (isset($$property) && strlen($$property) > 0){
                            $entity->$property = $$property;
                        }
                    }
                }
            }

            /*print_r($entities['bssPlan']->attributes);
            print_r($entities['quotaProfile']->attributes);
            //print_r($entities['freeZone']->attributes);
            echo "</pre>";
            exit();*/

            if($delete_freezone){

                if (!$entities['quotaProfile']->save()){
                    $errorMsg = "Error creating Quota Profile: " . json_encode($entities['quotaProfile']->getErrors());
                    throw new Exception($errorMsg, self::QUOTA_PROFILE_ERROR);
                }
                $entities['bssPlan']->quota_profile = $entities['quotaProfile']->quota_profile_id;
                $entities['bssPlan']->fz = null;

                if (!$entities['bssPlan']->save()){
                    $errorMsg = "Error actualizando FUS Service: " . json_encode($entities['bssPlan']->getErrors());
                    throw new Exception($errorMsg, self::BSS_PLAN_ERROR);
                }
                if (!empty($entities['freeZone'])){
                    if (!$entities['freeZone']->delete()){
                        $errorMsg = "Error deleting Freezone: " . json_encode($entities['freeZone']->getErrors());
                        throw new Exception($errorMsg, self::FREE_ZONE_ERROR);
                    }
                }
                $response = array_merge($entities['bssPlan']->attributes, $entities['quotaProfile']->attributes);
            }
            else{
                if (!$entities['quotaProfile']->save()){
                    $errorMsg = "Error creating Quota Profile: " . json_encode($entities['quotaProfile']->getErrors());
                    throw new Exception($errorMsg, self::QUOTA_PROFILE_ERROR);
                }
                if (!empty($entities['freeZone'])){
                    if (!$entities['freeZone']->save()){
                        $errorMsg = "Error creating Freezone: " . json_encode($entities['freeZone']->getErrors());
                        throw new Exception($errorMsg, self::FREE_ZONE_ERROR);
                    }
                }
                $entities['bssPlan']->quota_profile = $entities['quotaProfile']->quota_profile_id;
                $entities['bssPlan']->fz = !empty($entities['freeZone']) ? $entities['freeZone']->fz_id : null;


                if (!$entities['bssPlan']->save()){
                    $errorMsg = "Error creating FUS Service: " . json_encode($entities['bssPlan']->getErrors());
                    throw new Exception($errorMsg, self::BSS_PLAN_ERROR);
                }
                $response = empty($entities['freeZone']) ? array_merge($entities['bssPlan']->attributes, $entities['quotaProfile']->attributes) : array_merge($entities['bssPlan']->attributes, $entities['quotaProfile']->attributes, $entities['freeZone']->attributes);
            }

            $transaction->commit();
            return $response;

        }catch (Exception $e){
            $transaction->rollback();
            throw $e;
        }
    }


    /**
     * Register a terminal in Medea
     * 1. Check params.
     * 2. Check reset_day
     * 3. Check if the terminal already exit.
     * 4. Create terminal model.
     * 5. Save terminal in BD.
     *
     * @param sit_id,isp_id, bss_plan, reset_day
     */

    public function actionCreateTerminal()
    {
        Yii::log('LLAMADA API ALTA TERMINAL',CLogger::LEVEL_TRACE,"api");
        Yii::log('Datos POST: '.var_export($_POST, true), CLogger::LEVEL_INFO,"api");

        $this->api_name="createTerminal";

        // Comprobamos que tenemos los parametros esperados
        if(!(isset($_POST['sit_id'])&& isset($_POST['isp_id']) && isset($_POST['fus_service_id']) && isset($_POST['reset_day']))){
            Yii::log('Es necesario indicar el sit_id, el isp_id del terminal',CLogger::LEVEL_ERROR,"api");
            //$this->sendError('Es necesario indicar el sit_id, el isp_id, el fus_service_id y el reset_day');
            $error_message = 'MISSING_PARAMETERS -> '.$this->arrayToHTMLAttributes($_POST);
            //$error_message = 'MISSING_PARAMETERS';
            $this->sendError($error_message);
        }

        $sit_id = $_POST['sit_id'];
        $isp_id = $_POST['isp_id'];
        $fus_service_id = $_POST['fus_service_id'];
        $reset_day = $_POST['reset_day'];


        //Comprobamos en primer lugar si el terminal existe
        $criteria = new CDbCriteria();
        $criteria->condition='sit_id= :sit_id AND isp_id= :isp_id ';
        $criteria->params=array(':sit_id'=>$sit_id, ':isp_id'=>$isp_id);
        $terminal = Terminal::model()->find($criteria);
        if($terminal){
            Yii::log('Ya  existe el terminal indicado sit_id:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            $this->sendError('TERMINAL_ALREADY_IN_USE');
        }

        //Comprobamos que sit_id, isp_id son numero y mayores de cero y
        if ((!is_numeric($sit_id) || !is_numeric($isp_id) || $sit_id<=0 || $isp_id<=0)){
            Yii::log('sit_id e isp_id deben ser numero mayores que cero', CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'sit_id e isp_id deben ser numero mayores que cero');
            //$this->sendError('DATABASE_ERROR');
        }

        //Comprobamos que sit_id, isp_id tienen menos de 10 cifras
        if ((strlen($sit_id)>9 || strlen($isp_id)>9)){
            Yii::log('sit_id e isp_id deben tener menos de 10 cifras', CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'sit_id e isp_id deben tener menos de 10 cifras');
            //$this->sendError('DATABASE_ERROR');
        }

        //Obtengo el bss_plan_id que corresponde con ese fus_service_id
        $bss_plan = BssPlan::model()->findByAttributes(array('fus_service_id'=>$fus_service_id));

        if(!$bss_plan){
            Yii::log("El fus_service_id idicado no existe: ".$fus_service_id, CLogger::LEVEL_ERROR,"api");
            $this->sendError("FUS_SERVICE_NOT_FOUND -> ".$fus_service_id);
        }
        $bss_plan_id = $bss_plan->bss_plan_id;

        //Compruebo que el reset_day tenga sentido
        if($reset_day > 31 OR $reset_day<=0){
            Yii::log('El reset_day debe ser un numero entre el  1 y el 31',CLogger::LEVEL_ERROR,"api");
            $this->sendError('INCORRECT_RESET_DAY');
        }
        elseif($reset_day > 28){
            $reset_day = 28;
            Yii::log('Establecemos el reset day a 28. Valor enviado = '.$_POST['reset_day'],CLogger::LEVEL_INFO,"api");
        }



        //Creamos el nuevo terminal
        $terminal = new Terminal;

        $terminal->attributes = $_POST;
        $terminal->reset_day = $reset_day;
        $terminal->register_date = date('Y-m-d H:i:s');
        $terminal->bss_plan = $bss_plan_id;
        /*
        echo "<pre>";
        print_r($model);
        echo "</pre>";
        exit();
        */

        //Time Zone, de momento lo ponemos a +1 que corresponde a la zona de España
        $terminal->time_zone = 1;

        // Try to save the model

        //Ahora generamos el terminal historico
        $terminalHistory = new TerminalHistory();
        $terminalHistory->attributes = $terminal->attributes;
        $terminalHistory->upload_data_effective_residual = 0;
        $terminalHistory->total_data_effective_residual = 0;
        $terminalHistory->upload_data_real_residual = 0;
        $terminalHistory->total_data_real_residual = 0;


        $terminalHistory->insertion_date = date('Y-m-d H:i:s');
        $terminalHistory->motive = TerminalHistoryMotive::REGISTERED_TERMINAL;

        $transaction = Yii::app()->db->beginTransaction();

        try{
            if($terminal->save()){
                Yii::log('Creado terminal con terminal_id = '.$terminal->terminal_id,CLogger::LEVEL_TRACE,"api");
            }
            else{
                $error_model = $terminal->getErrors();
                Yii::log('No se puede crear terminal con ID: '. $terminal->terminal_id, CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $this->sendError('DATABASE_ERROR ->'.$error_model);
                //$this->sendError('DATABASE_ERROR');
            }

            if($terminalHistory->save()){
                Yii::log('Saved terminal with ID '. $terminalHistory->id. 'in terminal history',CLogger::LEVEL_TRACE,"api");

            }
            else{
                $error_model = $terminalHistory->getErrors();
                Yii::log('Could not move terminal with ID '. $terminal->terminal_id. ' in terminal history', CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $transaction->rollback();
                $this->sendError('DATABASE_ERROR ->'.$error_model);
                //$this->sendError('DATABASE_ERROR');
            }

            //Antes de mandar la respuesta de OK reseteamos el HUB
            try
            {
                $resetForward = Communicator::resetAccumulatedForwardVolume($terminal->isp_id, $terminal->sit_id);
                $msg = 'Reset Forward Volume for terminal '. $terminal->terminal_id . ' OK' .PHP_EOL ;
                Yii::log($msg, CLogger::LEVEL_TRACE, "api");
                Yii::log('Hub Response resetForward: '. var_export($resetForward, true). PHP_EOL, CLogger::LEVEL_TRACE, "api");

                $resetUpload = Communicator::resetAccumulatedReturnVolume($terminal->isp_id, $terminal->sit_id);
                $msg = 'Reset Upload Volume for terminal '. $terminal->terminal_id . ' OK' .PHP_EOL ;
                Yii::log($msg, CLogger::LEVEL_TRACE, "api");
                Yii::log('Hub Response resetUpload: '. var_export($resetUpload, true). PHP_EOL, CLogger::LEVEL_TRACE, "api");


            } catch (SoapFault $e) {

                // Damos de alta el terminal y mandamos el error de TERMINAL_NOT_RESET_HUB
                $transaction->commit();
                Yii::log('REGISTERED terminal with ID '. $terminal->terminal_id,CLogger::LEVEL_TRACE,"api");
                $msg_error = $e->getMessage().PHP_EOL;
                Yii::log('Error en el reseteo del Hub: '. var_export($msg_error, true). PHP_EOL, CLogger::LEVEL_TRACE, "api");
                $this->sendError('TERMINAL_NOT_RESET_HUB');


            }

            // Damos de alta el terminal y mandamos el OK
            $transaction->commit();
            Yii::log('REGISTERED terminal with ID '. $terminal->terminal_id,CLogger::LEVEL_TRACE,"api");
            Yii::log(var_export($this->returnAltaTerminal($terminal,$fus_service_id), true), CLogger::LEVEL_INFO,"api");
            $this->sendOK($this->returnAltaTerminal($terminal,$fus_service_id),true);

        }
        catch(Exception $e){
            $message_error = $e->getMessage();
            Yii::log('PROBLEMA EN LA BASE DE DATOS', CLogger::LEVEL_ERROR,"api");
            Yii::log(var_export($message_error, true), CLogger::LEVEL_ERROR,"api");
            $transaction->rollback();
            $this->sendError('DATABASE_ERRORR ->'.$message_error);
            //$this->sendError('DATABASE_ERROR');
        }


    }

    private function returnAltaTerminal($terminal, $fus_service_id){

        $return_arr = array();

        $return_arr += array('terminal_id'=>$terminal->terminal_id);
        $return_arr += array('sit_id'=>$terminal->sit_id);
        $return_arr += array('isp_id'=>$terminal->isp_id);
        $return_arr += array('fus_service_id'=>$fus_service_id);
        $return_arr += array('reset_day'=>$terminal->reset_day);
        $return_arr += array('upload_data_real'=>$terminal->upload_data_real);
        $return_arr += array('total_data_real'=>$terminal->total_data_real);
        $return_arr += array('upload_data_effective'=>$terminal->upload_data_effective);
        $return_arr += array('total_data_effective'=>$terminal->total_data_effective);
        $return_arr += array('fz_upload_gap'=>$terminal->fz_upload_gap);
        $return_arr += array('fz_total_gap'=>$terminal->fz_total_gap);
        $return_arr += array('register_date'=>$terminal->register_date);
        $return_arr += array('last_update_date'=>$terminal->last_update_date);
        $return_arr += array('gift_consumption'=>$terminal->gift_consumption);
        $return_arr += array('sla_status_id'=>$terminal->sla_status_id);
        $return_arr += array('time_zone'=>$terminal->time_zone);
        $return_arr += array('notification_total'=>$terminal->notification_total);
        $return_arr += array('notification_upload'=>$terminal->notification_upload);

        /*
        foreach ( $terminal->getAttributes() as $clave => $valor) {

            if(!is_null($valor)){
                if ($clave == 'isp_id'){
                    $return_arr += array($clave=>$valor);
                    $return_arr += array('fus_service_id'=>$fus_service_id);
                }
                elseif($clave == 'bss_plan'){
                    continue;
                }
                else{
                    $return_arr += [$clave=>$valor];
                }
            }
        }
        */
        return $return_arr;
    }




    /**
     * Decommissed hard a  terminal
     * 1. Check params
     * 2. Check if the terminal exist.
     * 3. Obtain terminal data from sit_id and isp_id
     * 4. Obtiene las quotas asociadas que esten BOOKED o ACTIVATED (es decir que esten en la tabla quota_extra)
     * 5. Borra dichas quotas de la tabla quota extra y las pasa a la tabla quota_extra_history
     * 5. Update terminal to decommissed state
     * 6. Saves terminal data in terminal_history
     *
     * @param sit_id,isp_id
     */

    public function actionDecommissionTerminal()
    {
        Yii::log('LLAMADA API BAJA TERMINAL',CLogger::LEVEL_TRACE,"api");
        Yii::log('Datos POST: '.var_export($_POST, true), CLogger::LEVEL_INFO,"api");

        $this->api_name="decommissionTerminal";

        //Comprobamos que tenemos los parametros esperados
        if(!(isset($_POST['sit_id'])&& isset($_POST['isp_id']))){
            Yii::log('Es necesario indicar el sit_id, el isp_id del terminal',CLogger::LEVEL_ERROR,"api");
            //$this->sendError('Necesito el sit_id, el isp_id del terminal');
            $error_message = 'MISSING_PARAMETERS -> '.$this->arrayToHTMLAttributes($_POST);
            //$error_message = 'MISSING_PARAMETERS';
            $this->sendError($error_message);
        }



        $sit_id = $_POST['sit_id'];
        $isp_id = $_POST['isp_id'];

        //Comprobamos que sit_id, isp_id son numero y mayores de cero
        if ((!is_numeric($sit_id) || !is_numeric($isp_id) || $sit_id<=0 || $isp_id<=0)){
            Yii::log('sit_id e isp_id deben ser numero mayores que cero', CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'sit_id e isp_id deben ser numero mayores que cero');
            //$this->sendError('DATABASE_ERROR');
        }

        /*
         * Inicialmente no comprobaremos si el terminal esta dado de baja, simplemente si existe o no en la
         * tabla terminales (terminales activos)
         */
        //Comprobamos si el terminal ya ha sido dado de baja debemos ver si existe en el historico con
        //motivo DECOMMISSED_TERMINAL
        /*
        $criteria = new CDbCriteria();
        $criteria->condition='sit_id= :sit_id AND isp_id= :isp_id AND motive = :motive_id ';
        $criteria->params=array(':sit_id'=>$sit_id, ':isp_id'=>$isp_id, ':motive_id'=>TerminalHistoryMotive::DECOMMISSED_TERMINAL);
        $terminal_history_tmp = TerminalHistory::model()->find($criteria);

        if($terminal_history_tmp ){
            Yii::log('El terminal indicado ya esta dado de baja:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            $this->sendError('El terminal ya esta dado de baja');
        }
        */

        //Comprobamos si el terminal existe
        $criteria = new CDbCriteria();
        $criteria->with = array('bssPlan',);
        $criteria->join = 'INNER JOIN bss_plan as k
                            ON (t.bss_plan = k.bss_plan_id)';
        /*
        $criteria->select = 't.terminal_id, t.sit_id, t.isp_id, t.reset_day, t.upload_data_real, t.total_data_real,
        t.upload_data_effective, t.total_data_effective, t.fz_upload_gap, t.fz_total_gap, t.register_date,
        t.last_update_date, t.gift_consumption, t.sla_status_id, t.time_zone, t.notification_total, t.notification_upload';
        */
        $criteria->condition='sit_id= :sit_id AND isp_id= :isp_id ';
        $criteria->params=array(':sit_id'=>$sit_id, ':isp_id'=>$isp_id);
        $terminal = Terminal::model()->find($criteria);
        if(!$terminal){
            Yii::log('No existe el terminal indicado sit_id:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            $this->sendError('TERMINAL_NOT_FOUND -> '.'sit_id:'.$sit_id.' -- isp_id:'.$isp_id);
        }

        /*
         * No podemos hacer el transaction en la baja de quotas extras asociadas
         * ya que si no las damos previamente de baja no podremos borrar el terminal
         * de la tabla terminal (baja) por los temas de las FK
         * Por ello comentaremos tambien todos los rollbacks implicados en el borrado de quotas extra.
         */
        $transaction = Yii::app()->db->beginTransaction();

        // Obtenemos las quotas booked o actived aosicadas al terminal en la tabla quota_extra
        $terminal_id = $terminal->terminal_id;
        $criteria = new CDbCriteria();
        $criteria->condition='terminal= :terminal_id';
        $criteria->params=array(':terminal_id'=>$terminal_id);
        $quotas_extra = QuotaExtra::model()->findAll($criteria);
        if($quotas_extra){
            Yii::log('Tenemos '.count($quotas_extra).' Quotas Extra para dar de baja:',CLogger::LEVEL_TRACE,"api");
            foreach($quotas_extra as $quota_extra){

                //Ahora generamos la quota extra historica
                $quotaExtraHistory = new QuotaExtraHistory();
                $quotaExtraHistory->attributes = $quota_extra->attributes;
                $quotaExtraHistory->status = QuotaExtraStatus::DECOMMISSED;
                $quotaExtraHistory->cancellation_date = date('Y-m-d H:i:s');
                $quotaExtraHistory->insertion_date = date('Y-m-d H:i:s');

                try{
                    if(!$quota_extra->delete()){
                        $error_model = $quota_extra->getErrors();
                        $transaction->rollback();
                        $this->sendError('Error en BD, Terminal');
                        break;
                    }

                    if($quotaExtraHistory->save()){
                        Yii::log('Saved Quota Extra in Quota Extra history',CLogger::LEVEL_TRACE,"api");

                    }
                    else{
                        $error_model = $quotaExtraHistory->getErrors();
                        Yii::log('Could not move Quota Extra in Quota Extra history', CLogger::LEVEL_ERROR,"api");
                        Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                        $transaction->rollback();
                        $this->sendError('DATABASE_ERROR -> '.$error_model);
                        //$this->sendError('DATABASE_ERROR');
                        break;

                    }
                    Yii::log('Decommissed Quota Extra con ID '.$quota_extra->extra_id,CLogger::LEVEL_TRACE,"api");

                }
                catch(Exception $e){
                    Yii::log('BD PROBLEM', CLogger::LEVEL_ERROR,"api");
                    Yii::log(var_export($e->getMessage(), true), CLogger::LEVEL_ERROR,"api");
                    $transaction->rollback();
                    $this->sendError('DATABASE_ERROR -> '.$e->getMessage());
                    //$this->sendError('DATABASE_ERROR');
                    break;
                }

            }
        }
        else{
            Yii::log('No existen quotas que dar de baja',CLogger::LEVEL_TRACE,"api");
        }

        // Comenzamos el transaction
        //$transaction = Yii::app()->db->beginTransaction();

        //Ponemos el state_id con el valor "DECOMMISSIONED"
        //Sacamos el id que corresponde a ese valor en la tabla terminal_state
        //$id1 = TerminalState::model()->find("literal='DECOMMISSIONED'")->getAttribute('id');
        //$id2 = TerminalState::model()->findByAttributes(array('literal'=>"DECOMMISSIONED"))->getAttribute('id');

        //En lugar de hacer lo de arriba usamos las constantes definidas en TerminalState
        //$terminal->state_id = TerminalState::DECOMMISSIONED;


        //Ahora generamos el terminal historico
        $terminalHistory = new TerminalHistory();
        $terminalHistory->attributes = $terminal->attributes;
        $terminalHistory->upload_data_effective_residual = 0;
        $terminalHistory->total_data_effective_residual = 0;
        $terminalHistory->upload_data_real_residual = 0;
        $terminalHistory->total_data_real_residual = 0;


        $terminalHistory->insertion_date = date('Y-m-d H:i:s');
        $terminalHistory->motive = TerminalHistoryMotive::DECOMMISSED_TERMINAL;



        try{
            if(!$terminal->delete()){
                $error_model = $terminal->getErrors();
                $transaction->rollback();
                isset($error_model)?$this->sendError($error_model):$this->sendError('Error en BD, Terminal');
            }

            if($terminalHistory->save()){
                Yii::log('Saved terminal with ID '. $terminalHistory->id. 'in terminal history',CLogger::LEVEL_TRACE,"api");

            }
            else{
                $error_model = $terminalHistory->getErrors();
                Yii::log('Could not move terminal with ID '. $terminal->terminal_id. ' in terminal history', CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $transaction->rollback();
                $this->sendError('DATABASE_ERROR -> '.$error_model);
                //$this->sendError('DATABASE_ERROR');

            }
            $transaction->commit();
            Yii::log('DECOMMISSIONED terminal with ID '. $terminal->terminal_id,CLogger::LEVEL_TRACE,"api");
            Yii::log('REMOVE THE CORRESPONDING ACTION PENDING...',CLogger::LEVEL_INFO,"api");
            // Por ultimo borramos las acciones pendientes y las pasamos al historico
            $this->archiveActionPending($sit_id,$isp_id);
            /*
             * Si hemos llegado hasta aqui, independientemente de como haya ido el proceso de archivado de
             * acciones pendientes se envia un mensaje de OK de respuesta
             */

            $this->sendOK($this->return_data_terminal($terminal));
            //$this->sendOK($terminal->getAttributes(),true);

        }
        catch(Exception $e){
            Yii::log('BD PROBLEM', CLogger::LEVEL_ERROR,"api");
            $transaction->rollback();
            $message_error = $e->getMessage();
            Yii::log(var_export($message_error, true), CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.$message_error);
            //$this->sendError('DATABASE_ERROR');

        }




    }

    private function return_data_terminal($terminal){
        $return_arr = array();
        foreach ( $terminal->getAttributes() as $clave => $valor) {

            if(!is_null($valor)){
                if ($clave == 'isp_id'){
                    $return_arr += array($clave=>$valor);
                    //$return_arr += array('fus_service_id'=>$fus_service_id);
                    $return_arr += array('fus_service_id'=>$terminal->bssPlan->fus_service_id);

                }
                elseif ($clave == 'bss_plan'){
                    continue;
                }
                else{
                    $return_arr += array($clave=>$valor);
                }
            }
        }
        return $return_arr;
    }

    /**
     * Archive the action pending in action_history when a terminal is decommissed
     * 1. Obtain all action pending for the terminal decommissed (sit_id and isp_id)
     * 2. Move these action pending to action_history.
     * 3. Remove these actions pending from DB
     * @param sit_id, isp_id,
     */

    private function archiveActionPending($sit_id, $isp_id){
        try{
            //Obtain all action pending for the current terminal ($sit_id, isp_id)
            $criteria = new CDbCriteria();
            $criteria->condition='sit_id= :sit_id AND isp_id= :isp_id ';
            $criteria->params=array(':sit_id'=>$sit_id, ':isp_id'=>$isp_id);
            $actions_pending = ActionPending::model()->findAll($criteria);
            if(!$actions_pending){
                Yii::log('NO Action Pendind for this Terminal --> sit_id:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            }
            else{
                foreach($actions_pending as $action_pending){

                    //Set the action pending history
                    $action_history = new ActionHistory();
                    $action_history->attributes = $action_pending->attributes;
                    $action_history->info_error = "CANCELADA POR BAJA DE TERMINAL";
                    $action_history->action_completion_date = date('Y-m-d H:i:s');

                    try{
                        // Delete the action pending
                        if(!$action_pending->delete()){
                            $error_model = $action_pending->getErrors();
                            Yii::log('ERROR REMOVING ACTION PENDING (You must remove it manually) -> id: '.$action_pending->action_id,CLogger::LEVEL_TRACE,"api");
                            Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                            continue;
                        }
                        Yii::log('Decommissed Action Pending con ID '.$action_pending->action_id,CLogger::LEVEL_TRACE,"api");

                        // Archive de action pending in History
                        if($action_history->save()){
                            Yii::log('Saved Action Pending in Quota Extra history',CLogger::LEVEL_INFO,"api");

                        }
                        else{
                            $error_model = $action_history->getErrors();
                            Yii::log('ERROR moving Action Pending in Action Pending History', CLogger::LEVEL_ERROR,"api");
                            Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                            continue;

                        }

                    }
                    catch(Exception $e){
                        Yii::log('BD PROBLEM', CLogger::LEVEL_ERROR,"api");
                        Yii::log(var_export($e->getMessage(), true), CLogger::LEVEL_ERROR,"api");
                        continue;
                    }
                }
            }

        }
        catch(Exception $e){
            Yii::log('ERROR IN ARCHIVE ACTION PENDING '.$action_pending->action_id,CLogger::LEVEL_ERROR,"api");
            Yii::log(var_export($e->getMessage(), true), CLogger::LEVEL_ERROR,"api");

        }
    }

    /**
     * Create a quota extra associated to terminal
     * 1. Check params
     * 2. Check if the terminal exist.
     * 3. Check if the terminal already  decommissed
     * 4. Obtain terminal data from sit_id and isp_id
     * 5. Create quota extra associated to terminal
     *
     * @param sit_id, isp_id, quota
     */

    public function actionCreateQuotaExtra()
    {
        Yii::log('LLAMADA API ALTA QUOTA EXTRA',CLogger::LEVEL_TRACE,"api");
        Yii::log('Datos POST: '.var_export($_POST, true), CLogger::LEVEL_INFO,"api");

        $this->api_name="createQuotaExtra";

        // Comprobamos que tenemos los parametros esperados
        if(!(isset($_POST['sit_id'])&& isset($_POST['isp_id']) && isset($_POST['quota']))){
            Yii::log('Es necesario indicar el sit_id, el isp_id del terminal y la quota en GB',CLogger::LEVEL_ERROR,"api");
            //$this->sendError('Es necesario indicar el sit_id, el isp_id y la quota');
            $error_message = 'MISSING_PARAMETERS -> '.$this->arrayToHTMLAttributes($_POST);
            //$error_message = 'MISSING_PARAMETERS';
            $this->sendError($error_message);
        }

        $sit_id = $_POST['sit_id'];
        $isp_id = $_POST['isp_id'];
        $quota = $_POST['quota'];
        // Hacemos lo siguiente porque cuando se envia un numero mayor de 14 cifras por post (formulario)
        // php lo transforma automatiocamente en algo parecido a esto 12345 E 12
        //$quota = number_format(str_replace(' ', '', $quota_tmp),0,'','');


        //Comprobamos que sit_id, isp_id son numero y mayores de cero
        if ((!is_numeric($sit_id) || !is_numeric($isp_id) || $sit_id<=0 || $isp_id<=0)){
            Yii::log('sit_id e isp_id deben ser numero mayores que cero', CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'sit_id e isp_id deben ser numero mayores que cero');
            //$this->sendError('DATABASE_ERROR');
        }

        //Comprobamos que la quota sea menor que 100 TB
        if ($quota>99999){
            Yii::log('La quota (GB) debe ser menor de 100 TB', CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'La quota no puede exceder de 14 digitos');
            //$this->sendError('DATABASE_ERROR');
        }

        //Comprobamos que la quota no exceda los 14 digitos
        if (strlen($quota)>14){
            Yii::log('La quota no puede exceder de 14 digitos', CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'La quota no puede exceder de 14 digitos');
            //$this->sendError('DATABASE_ERROR');
        }

        //Comprobamos que la quota sea un numero
        if (!is_numeric($quota)){
            Yii::log('La quota debe ser un numero', CLogger::LEVEL_ERROR,"api");
            Yii::log('La quota debe ser un numero: '.$quota, CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'La quota debe ser un numero');
            //$this->sendError('DATABASE_ERROR');
        }
        //Comprobamos que la quota sea mayor que cero
        elseif($quota<=0){
            Yii::log('La quota debe ser mayor que cero', CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'La quota debe ser mayor que cero');
            //$this->sendError('DATABASE_ERROR');
        }

        // Comprobamos que el terminal existe y extraemos el terminal_id (es el que usa la tabla quota extra)
        // a partir del sit_id y el isp_id

        $sit_id = $_POST['sit_id'];
        $isp_id = $_POST['isp_id'];
        $criteria = new CDbCriteria();
        $criteria->condition='sit_id= :sit_id AND isp_id= :isp_id ';
        $criteria->params=array(':sit_id'=>$sit_id, ':isp_id'=>$isp_id);
        $terminal = Terminal::model()->find($criteria);
        if(!$terminal){
            Yii::log('No existe el terminal indicado sit_id:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            $this->sendError('TERMINAL_NOT_FOUND -> '.'sit_id:'.$sit_id.' -- isp_id:'.$isp_id);
        }

        $term_id = $terminal->getAttribute('terminal_id');

        $quota_extra = new QuotaExtra();

        $booking = date('Y-m-d H:i:s');
        // Si no nos llega una fecha de expiracion será un mes y hasta el siguiente reseteo
        // Puede ser que no venga definida la variable o que venga igual a false

        if (!isset($_POST['expired_date'])){
            $reset_day = $terminal->reset_day;
            $booking_day = date('d');
            $format = 'Y-m-'.$reset_day.' 00:00:00';
            if ($booking_day>$reset_day)
                $expire = date ($format,strtotime($booking." +2 month"));
            else
                $expire = date ($format,strtotime($booking." +1 month"));
        }
        else{
            $expire = date('Y-m-d H:i:s',strtotime($_POST['expired_date']));
            if($expire<=$booking)
                $this->sendError('INCORRECT_EXPIRATION_DATE ->'.$expire);
        }

        $quota_extra->booking_date = $booking;
        $quota_extra->expiration_date = $expire;
        // La quota viene en GB y debemos pasarla a bytes para guardarla en base de datos
        $quota_extra->quota = $quota*1000000000;
        $quota_extra->terminal = $term_id;
        $quota_extra->status = QuotaExtraStatus::BOOKED;

        // Try to save the quota
        try{
            if($quota_extra->save()){
                Yii::log('Creada Quota con id = '.$quota_extra->extra_id,CLogger::LEVEL_TRACE,"api");
                //$this->sendOK($quota_extra->getAttributes(),true);
                //Generamos el array de vuelta
                $this->sendOK($this->returnAltaQuotaExtra($quota_extra),true);
            }
            else{
                $error_model = $quota_extra->getErrors();
                Yii::log('No se ha podido dar de alta la quota para el terminal sit_id:'.$sit_id.' -- isp_id:'.$isp_id, CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $this->sendError('DATABASE_ERROR -> '.$error_model);
                //$this->sendError('DATABASE_ERROR');
            }

        }
        catch(Exception $e){
            Yii::log('No se ha podido dar la quota de alta para el terminal sit_id:'.$sit_id.' -- isp_id:'.$isp_id, CLogger::LEVEL_ERROR,"api");
            Yii::log(var_export($e->getMessage(), true), CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.$e->getMessage());
            //$this->sendError('DATABASE_ERROR');
        }

    }

    private function returnAltaQuotaExtra($quota_extra){

        $return_arr = array();

        $return_arr += array('extra_id'=>$quota_extra->extra_id);
        $return_arr += array('terminal'=>$quota_extra->terminal);
        $return_arr += array('status'=>$quota_extra->status);
        $return_arr += array('traffic_type'=>$quota_extra->traffic_type);
        $return_arr += array('quota'=>$quota_extra->quota);
        $return_arr += array('initial_consumption'=>$quota_extra->initial_consumption);
        $return_arr += array('consumed_quota'=>$quota_extra->consumed_quota);
        $return_arr += array('booking_date'=>$quota_extra->booking_date);
        $return_arr += array('activation_date'=>$quota_extra->activation_date);
        $return_arr += array('completion_date'=>$quota_extra->completion_date);
        $return_arr += array('expiration_date'=>$quota_extra->expiration_date);
        $return_arr += array('notification_total'=>$quota_extra->notification_total);

        return $return_arr;
    }

    /**
     * Delete a terminal
     * 1. Check params
     * 2. Check if the quota exist.
     * 3. Check if is activated
     * 3. Delete quota from quota_extra
     * 4. Saves quota data in quota_extra_history
     *
     * @param extra_id
     */

    public function actionDeleteQuotaExtra()
    {
        Yii::log('LLAMADA API BAJA QUOTA EXTRA',CLogger::LEVEL_TRACE,"api");
        Yii::log('Datos POST: '.var_export($_POST, true), CLogger::LEVEL_INFO,"api");

        $this->api_name="deleteQuotaExtra";

        //Comprobamos que tenemos los parametros esperados
        if(!isset($_POST['extra_id'])){
            Yii::log('Es necesario indicar el extra_id de la Quota Extra',CLogger::LEVEL_ERROR,"api");
            //$this->sendError('Es necesario indicar el extra_id de la Quota Extra');
            $error_message = 'MISSING_PARAMETERS ->'.$this->arrayToHTMLAttributes($_POST);
            //$error_message = 'MISSING_PARAMETERS';
            $this->sendError($error_message);
        }

        $extra_id = $_POST['extra_id'];
        $quota_extra = QuotaExtra::model()->findByPk($extra_id);
        if(!$quota_extra){
            Yii::log('No existe la Quota Extra indicada extra_id:'.$extra_id,CLogger::LEVEL_ERROR,"api");
            $this->sendError('ADDITIONAL_QUOTA_NOT_FOUND -> '.$extra_id);
        }

        if($quota_extra->status == QuotaExtraStatus::ACTIVATED ){
            Yii::log('La Quota Extra ya ha sido activada y no se puede dar de baja',CLogger::LEVEL_ERROR,"api");
            $this->sendError('QUOTA_IN_USE');
        }

        //Ahora generamos la quota extra historica
        $quotaExtraHistory = new QuotaExtraHistory();
        $quotaExtraHistory->attributes = $quota_extra->attributes;
        $quotaExtraHistory->status = QuotaExtraStatus::CANCELLED;
        $quotaExtraHistory->cancellation_date = date('Y-m-d H:i:s');
        $quotaExtraHistory->insertion_date = date('Y-m-d H:i:s');


        $transaction = Yii::app()->db->beginTransaction();

        try{
            if(!$quota_extra->delete()){
                $error_model = $quota_extra->getErrors();
                Yii::log('No se ha podido dar de baja la quota extra: '.$extra_id, CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $this->sendError('DATABASE_ERROR -> '.$error_model);
                //$this->sendError('DATABASE_ERROR');
            }

            if($quotaExtraHistory->save()){
                Yii::log('Saved Quota Extra in Quota Extra history',CLogger::LEVEL_TRACE,"api");

            }
            else{
                $error_model = $quotaExtraHistory->getErrors();
                Yii::log('Could not move Quota Extra in Quota Extra history', CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $transaction->rollback();
                $this->sendError('DATABASE_ERROR -> '.$error_model);
                //$this->sendError('DATABASE_ERROR');
            }
            $transaction->commit();
            Yii::log('Cancelled Quota Extra con ID '. $extra_id,CLogger::LEVEL_TRACE,"api");
            $this->sendOK($quota_extra->getAttributes(),true);

        }
        catch(Exception $e){
            Yii::log('BD PROBLEM', CLogger::LEVEL_ERROR,"api");
            Yii::log(var_export($e->getMessage(), true), CLogger::LEVEL_ERROR,"api");
            $transaction->rollback();
            $this->sendError('DATABASE_ERROR -> '.$e->getMessage());
            //$this->sendError('DATABASE_ERROR');
        }
    }

    /**
     * Delete a terminal
     * 1. Check params
     * 2. Check if the terminal exist.
     * 3. Check if the terminal is not decommissioned.
     * 3. Check if the fus_service_id  exist.
     * 4. Update bss_plan of Terminal.
     *
     * @param sit_id, isp_id, bss_plan_id
     */
    public function actionUpdateTerminalPlan()
    {
        Yii::log('LLAMADA API ACTUALIZAR TERMINAL',CLogger::LEVEL_TRACE,"api");
        Yii::log('Datos POST: '.var_export($_POST, true), CLogger::LEVEL_INFO,"api");

        $this->api_name="updateTerminalPlan";

        //Comprobamos que tenemos los parametros esperados
        if(!(isset($_POST['sit_id'])&& isset($_POST['isp_id']) && isset($_POST['fus_service_id']))){
            Yii::log('Es necesario indicar el sit_id, el isp_id y el nuevo bss_plan_id del terminal',CLogger::LEVEL_ERROR,"api");
            //$this->sendError('Es necesario indicar el sit_id, el isp_id y el nuevo fus_service_id del terminal');
            $error_message = 'MISSING_PARAMETERS -> '.$this->arrayToHTMLAttributes($_POST);
            //$error_message = 'MISSING_PARAMETERS';
            $this->sendError($error_message);
        }

        $sit_id = $_POST['sit_id'];
        $isp_id = $_POST['isp_id'];
        $fus_service_id = $_POST['fus_service_id'];

        //Comprobamos que sit_id, isp_id son numero y mayores de cero
        if ((!is_numeric($sit_id) || !is_numeric($isp_id) || $sit_id<=0 || $isp_id<=0)){
            Yii::log('sit_id e isp_id deben ser numero mayores que cero', CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'sit_id e isp_id deben ser numero mayores que cero');
            //$this->sendError('DATABASE_ERROR');
        }

        $criteria = new CDbCriteria();
        $criteria->with = array('bssPlan',);
        $criteria->join = 'INNER JOIN bss_plan as k
                            ON (t.bss_plan = k.bss_plan_id)';
        /*
        $criteria->select = 't.terminal_id, t.sit_id, t.isp_id, t.reset_day, t.upload_data_real, t.total_data_real,
        t.upload_data_effective, t.total_data_effective, t.fz_upload_gap, t.fz_total_gap, t.register_date,
        t.last_update_date, t.gift_consumption, t.sla_status_id, t.time_zone, t.notification_total, t.notification_upload';
        */
        $criteria->condition='sit_id= :sit_id AND isp_id= :isp_id ';
        $criteria->params=array(':sit_id'=>$sit_id, ':isp_id'=>$isp_id);
        $terminal = Terminal::model()->find($criteria);
        if(!$terminal){
            Yii::log('No existe el terminal indicado sit_id:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            $this->sendError('TERMINAL_NOT_FOUND -> '.'sit_id:'.$sit_id.' -- isp_id:'.$isp_id);
        }

        //Obtengo el bss_plan_id que corresponde con ese fus_service_id
        $bss_plan = BssPlan::model()->findByAttributes(array('fus_service_id'=>$fus_service_id));

        if(!$bss_plan){
            Yii::log("El fus_service_id idicado no existe: ".$fus_service_id, CLogger::LEVEL_ERROR,"api");
            $this->sendError("FUS_SERVICE_NOT_FOUND -> ".$fus_service_id);
        }
        $bss_plan_id = $bss_plan->bss_plan_id;


        //Guardamos el bss_plan original para tracear el cambio
        $bss_plan_id_orig = $terminal->bss_plan;



        //Ahora generamos el terminal historico
        $terminalHistory = new TerminalHistory();
        $terminalHistory->attributes = $terminal->attributes;
        $terminalHistory->upload_data_effective_residual = 0;
        $terminalHistory->total_data_effective_residual = 0;
        $terminalHistory->upload_data_real_residual = 0;
        $terminalHistory->total_data_real_residual = 0;


        $terminalHistory->insertion_date = date('Y-m-d H:i:s');
        //todo Confirmar si guardo en historico el terminal con el bss cambiado o sin cambiar
        $terminalHistory->motive = TerminalHistoryMotive::TERMINAL_MODIFICATION;

        //Cambio el bss_plan
        $terminal->bss_plan = $bss_plan_id;
        $terminal->bssPlan = $bss_plan;

        $transaction = Yii::app()->db->beginTransaction();

        try{
            if(!$terminal->update()){
                $error_model = $terminal->getErrors();
                Yii::log('Could not update terminal with ID '. $terminal->terminal_id. ' in terminal history', CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $this->sendError('DATABASE_ERROR -> '.$error_model);
                //$this->sendError('DATABASE_ERROR');
            }

            if($terminalHistory->save()){
                Yii::log('Saved terminal with ID '. $terminalHistory->id. 'in terminal history',CLogger::LEVEL_TRACE,"api");

            }
            else{
                $error_model = $terminalHistory->getErrors();
                Yii::log('Could not move terminal with ID '. $terminal->terminal_id. ' in terminal history', CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $transaction->rollback();
                $this->sendError('DATABASE_ERROR -> '.$error_model);
                //$this->sendError('DATABASE_ERROR');
            }
            $transaction->commit();
            Yii::log('MODIFIED bss_plan ('.$bss_plan_id_orig.' --> '.$bss_plan_id.') from terminal with ID '. $terminal->terminal_id,CLogger::LEVEL_TRACE,"api");
            $this->sendOK($this->return_data_terminal($terminal));
            //$this->sendOK($terminal->getAttributes(),true);

        }
        catch(Exception $e){
            Yii::log('DATABASE_ERROR', CLogger::LEVEL_ERROR,"api");
            Yii::log(var_export($e->getMessage(), true), CLogger::LEVEL_ERROR,"api");
            $transaction->rollback();
            $this->sendError('DATABASE_ERROR -> '.$e->getMessage());
            //$this->sendError('DATABASE_ERROR');
        }

    }

    /**
     * Delete a terminal
     * 1. Check params
     * 2. Check if the terminal exist.
     * 3. Check if the terminal is not decommissioned.
     * 3. Check if the fus_service_id  exist.
     * 4. Update bss_plan of Terminal.
     *
     * @param sit_id, isp_id, bss_plan_id
     */
    public function actionUpdateTerminal()
    {
        Yii::log('API UPDATE TERMINAL',CLogger::LEVEL_TRACE,"api");
        Yii::log('POST Data: '.var_export($_POST, true), CLogger::LEVEL_INFO,"api");

        $this->api_name="updateTerminal";

        //Comprobamos que tenemos los parametros esperados
        if(!(isset($_POST['sit_id'])&& isset($_POST['isp_id']))){
            Yii::log('You must indicate the sit_id and the isp_id of terminal',CLogger::LEVEL_ERROR,"api");
            //$this->sendError('Es necesario indicar el sit_id y el isp_id ');
            $error_message = 'MISSING_PARAMETERS -> '.$this->arrayToHTMLAttributes($_POST);
            //$error_message = 'MISSING_PARAMETERS';
            $this->sendError($error_message);
        }

        $sit_id = $_POST['sit_id'];
        $isp_id = $_POST['isp_id'];

        //Comprobamos que sit_id, isp_id son numero y mayores de cero
        if ((!is_numeric($sit_id) || !is_numeric($isp_id) || $sit_id<=0 || $isp_id<=0)){
            Yii::log('sit_id and isp_id must be greater than zero', CLogger::LEVEL_ERROR,"api");
            $this->sendError('DATABASE_ERROR -> '.'sit_id and isp_id must be greater than zero');
            //$this->sendError('DATABASE_ERROR');
        }

        $criteria = new CDbCriteria();
        $criteria->with = array('bssPlan',);
        $criteria->join = 'INNER JOIN bss_plan as k
                            ON (t.bss_plan = k.bss_plan_id)';
        /*
        $criteria->select = 't.terminal_id, t.sit_id, t.isp_id, t.reset_day, t.upload_data_real, t.total_data_real,
        t.upload_data_effective, t.total_data_effective, t.fz_upload_gap, t.fz_total_gap, t.register_date,
        t.last_update_date, t.gift_consumption, t.sla_status_id, t.time_zone, t.notification_total, t.notification_upload';
        */
        $criteria->condition='sit_id= :sit_id AND isp_id= :isp_id ';
        $criteria->params=array(':sit_id'=>$sit_id, ':isp_id'=>$isp_id);
        $terminal = Terminal::model()->find($criteria);
        if(!$terminal){
            Yii::log('This terminal does not exist sit_id:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            $this->sendError('TERMINAL_NOT_FOUND -> '.'sit_id:'.$sit_id.' -- isp_id:'.$isp_id);
        }

        // Si tenemos cambio de plan
        if(isset($_POST['fus_service_id'])){
            $fus_service_id = $_POST['fus_service_id'];
            //Obtengo el bss_plan_id que corresponde con ese fus_service_id
            $bss_plan = BssPlan::model()->findByAttributes(array('fus_service_id'=>$fus_service_id));

            if(!$bss_plan){
                Yii::log("This FUS Service does not exist: ".$fus_service_id, CLogger::LEVEL_ERROR,"api");
                $this->sendError("FUS_SERVICE_NOT_FOUND -> ".$fus_service_id);
            }
            Yii::log('FUS Service change:  '.$terminal->bssPlan->fus_service_id.' to '.$fus_service_id, CLogger::LEVEL_INFO,"api");
            //Actualizamos el terminal tanto el campo bss_plan como su relacion bssPlan
            $terminal->bss_plan = $bss_plan->bss_plan_id;
            $terminal->bssPlan = $bss_plan;
        }

        // Si tenemos cambio de reset day
        if(isset($_POST['reset_day'])){
            //Comprobamos que sea un numero entre 1 y 28
            $reset_day = $_POST['reset_day'];
            if($reset_day>=1 && $reset_day<=28){
                Yii::log('Reset Day change: '.$terminal->reset_day.' to '.$reset_day, CLogger::LEVEL_INFO,"api");
                //Actualizamos el reset_day del terminal
                $terminal->reset_day = $reset_day;
            }
            else{
                Yii::log('RESET DAY MUST BE BETWEEN 1 AND 28', CLogger::LEVEL_ERROR,"api");
                $this->sendError("RESET DAY MUST BE BETWEEN 1 AND 28");
            }

        }


        //Ahora generamos el terminal historico
        $terminalHistory = new TerminalHistory();
        $terminalHistory->attributes = $terminal->attributes;
        $terminalHistory->upload_data_effective_residual = 0;
        $terminalHistory->total_data_effective_residual = 0;
        $terminalHistory->upload_data_real_residual = 0;
        $terminalHistory->total_data_real_residual = 0;


        $terminalHistory->insertion_date = date('Y-m-d H:i:s');
        //todo Confirmar si guardo en historico el terminal con el bss cambiado o sin cambiar
        $terminalHistory->motive = TerminalHistoryMotive::TERMINAL_MODIFICATION;


        //Cambio el bss_plan si viene dado
        //if(isset($_POST['fus_service_id'])


        $transaction = Yii::app()->db->beginTransaction();

        try{
            if(!$terminal->update()){
                $error_model = $terminal->getErrors();
                Yii::log('Can not update terminal with ID '. $terminal->terminal_id. ' in terminal history', CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $this->sendError('DATABASE_ERROR -> '.$error_model);
                //$this->sendError('DATABASE_ERROR');
            }

            if($terminalHistory->save()){
                Yii::log('Saved terminal with ID '. $terminalHistory->id. ' in Terminal History',CLogger::LEVEL_TRACE,"api");

            }
            else{
                $error_model = $terminalHistory->getErrors();
                Yii::log('Could not move terminal with ID '. $terminal->terminal_id. ' in terminal history', CLogger::LEVEL_ERROR,"api");
                Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                $transaction->rollback();
                $this->sendError('DATABASE_ERROR -> '.$error_model);
                //$this->sendError('DATABASE_ERROR');
            }
            $transaction->commit();
            Yii::log('UPDATE TERMINAL with ID '. $terminal->terminal_id,CLogger::LEVEL_TRACE,"api");
            $this->sendOK($this->return_data_terminal($terminal));
            //$this->sendOK($terminal->getAttributes(),true);

        }
        catch(Exception $e){
            Yii::log('DATABASE_ERROR', CLogger::LEVEL_ERROR,"api");
            Yii::log(var_export($e->getMessage(), true), CLogger::LEVEL_ERROR,"api");
            $transaction->rollback();
            $this->sendError('DATABASE_ERROR -> '.$e->getMessage());
            //$this->sendError('DATABASE_ERROR');
        }

    }

    /**
     * Consulting to terminal
     * 1. Check params
     * 2. Check if the terminal exist.
     * 4. Obtain terminal data from sit_id and isp_id with his quotas extras
     *
     * @param sit_id, isp_id
     */

    public function actionViewTerminal()
    {
        Yii::log('LLAMADA API CONSULTAR TERMINAL',CLogger::LEVEL_TRACE,"api");
        Yii::log('Datos POST: '.var_export($_POST, true), CLogger::LEVEL_INFO,"api");

        $this->api_name = "viewTerminal";

        //Comprobamos que tenemos los parametros esperados
        if(!(isset($_POST['sit_id'])&& isset($_POST['isp_id']))){
            Yii::log('Es necesario indicar el sit_id, el isp_id del terminal',CLogger::LEVEL_ERROR,"api");
            //$this->sendError('Es necesario indicar el sit_id, el isp_id del terminal');
            $error_message = 'MISSING_PARAMETERS -> '.$this->arrayToHTMLAttributes($_POST);
            //$error_message = 'MISSING_PARAMETERS';
            $this->sendError($error_message);
        }

        $sit_id = $_POST['sit_id'];
        $isp_id = $_POST['isp_id'];
        $criteria = new CDbCriteria();
        $criteria->with = array('bssPlan',);
        $criteria->join = 'INNER JOIN bss_plan as k
                            ON (t.bss_plan = k.bss_plan_id)';
        /*
        $criteria->select = 't.terminal_id, t.sit_id, t.isp_id, t.reset_day, t.upload_data_real, t.total_data_real,
        t.upload_data_effective, t.total_data_effective, t.fz_upload_gap, t.fz_total_gap, t.register_date,
        t.last_update_date, t.gift_consumption, t.sla_status_id, t.time_zone, t.notification_total, t.notification_upload';
        */
        $criteria->condition='sit_id= :sit_id AND isp_id= :isp_id ';
        $criteria->params=array(':sit_id'=>$sit_id, ':isp_id'=>$isp_id);
        $terminal = Terminal::model()->find($criteria);
        if(!$terminal){
            Yii::log('No existe el terminal indicado sit_id:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            $this->sendError('TERMINAL_NOT_FOUND -> '.'sit_id:'.$sit_id.' -- isp_id:'.$isp_id);
        }
        /*
        if($terminal->state_id == TerminalState::DECOMMISSIONED ){
            Yii::log('El terminal indicado esta dado de baja:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            $this->sendError('El terminal esta dado de baja');
        }
        */

        // El siguiente paso es sacar la informacion de todas las quotas extras
        $id_terminal = $terminal->terminal_id;

        //Available quotas:
        $criteria = new CDbCriteria();
        $criteria->condition='terminal= :id_terminal ';
        $criteria->params=array(':id_terminal'=>$id_terminal);
        $quotas_availables = QuotaExtra::model()->findAll($criteria);
        $arr_available_quotas = array();

        foreach ($quotas_availables as $item){
            $arr_available_quotas[]= $item->getAttributes();
        }

        //Historic quotas:
        //Obtengo la fecha de hace 6 meses
        $date_six = date ('Y-m-d H:i:s',strtotime("now -6 month"));

        $criteria = new CDbCriteria();
        $criteria->condition='terminal= :id_terminal AND booking_date > :date_six ';
        $criteria->params=array(':id_terminal'=>$id_terminal,':date_six'=>$date_six);
        $quotas_historic = QuotaExtraHistory::model()->findAll($criteria);
        $arr_historic_quotas = array();

        foreach ($quotas_historic as $item){
            $arr_historic_quotas[]= $item->getAttributes();
        }
        // Genero la respuesta

        $arr = array(
            "error_code" => 0,
            "data" => array(
                "terminal"=>$this->return_data_terminal($terminal),
                "available_quotas"=>$arr_available_quotas,
                "historic_quotas"=>$arr_historic_quotas
            ),
        );
        //Es un caso especial y no pasa por el sendOK
        $this->setApiCall("N.A",0);
        print_r(json_encode($arr));

    }

    public function actionViewLog(){

        //Logger::log(CLogger::LEVEL_INFO, "Get Fus Services, Parameters received: " . var_export($_POST, true), Logger::CAT_API, false);
        Yii::log('LLAMADA API VIEW LOG',CLogger::LEVEL_TRACE,"api");
        Yii::log('Datos POST: '.var_export($_POST, true), CLogger::LEVEL_INFO,"api");

        $this->api_name = "viewLog";

        $logs = array();
        $response = null;

        /*
         * Obtenemos todas las llamadas a las APIs de la tabla apì_call con las siguientes restricciones
         * No se muestran las llamadas a las funcions getBssPlan ni las llamadas al propio viewLog
         * Se restringe a 1000 el numero de registros obtenidos, ordenados por fecha
         */
        $log_model = new ApiCall();
        $criteria = new CDbCriteria();
        $criteria->condition='api_name!= "getBssPlan" AND api_name!= "viewLog"';
        $criteria->order = "date_call DESC";
        $criteria->limit = 1000;
        $logs = $log_model->findAll($criteria);

        /*
         * Vamos a recorrer los atributos para introducir un espacio en blanco cada 100 caracteres en
         * el campo query_param para que se pinte bonito en la tabla que se muestra en la Interfaz web
         */

        if (!empty($logs)){
            foreach($logs as $log){
                $response[] = $log->attributes;
            }
            $this->sendOk($response, false);
        }else{
            $response = "No Api-call found in Data Base.";
            $this->sendError($response);
        }

        Logger::log(CLogger::LEVEL_INFO, "Response: " . json_encode($response), Logger::CAT_API, false);
    }

    private function checkUserPass($user, $pass){

        $error_message = null;

        if (empty($user) || empty($pass)){
            Yii::log('Falta el usuario o la contraseña',CLogger::LEVEL_ERROR,"api");
            $error_message = 'MISSING USER/PASSWORD';
        }else{
            $record = UserApi::model()->findByAttributes(array('username' => $user));
            if($record === null){
                Yii::log("No existe el usuario en UserApi", CLogger::LEVEL_ERROR,"api");
                //else if($record->password!==md5($this->password))
                $error_message = 'INVALID USER/PASSWORD';
            }else if($record->password !== $pass){
                Yii::log("La password es incorrecta", CLogger::LEVEL_ERROR,"api");
                $error_message = 'INVALID USER/PASSWORD';
            }
        }
        return $error_message;
    }

    private function setApiCall($error_message,$code){
        $api_call = new ApiCall();
        //$api_call->api_name = Yii::app()->controller->action->id;
        $api_call->api_name = $this->api_name;
        $api_call->date_call = date("Y-m-d H:i:s");
        if (isset($_POST['user_frontend']))
            $api_call->user = $_POST['user_frontend'];
        else
            $api_call->user = 'ViaAPI';
        // Eliminamos el usuario y password API de los parametros para no mostrarlos en BD
        $query_param = $_POST;
        unset($query_param['user_api']);
        unset($query_param['pass_api']);
        $api_call->query_param = json_encode($query_param);
        if($code == 0){
            $api_call->status = "OK";
            $api_call->return_message = "N.A";
        }
        else{
            $api_call->status = "KO";
            $api_call->return_message = json_encode($error_message);
        }
        try{
           $api_call->save();
        }
        catch(Exception $e){
            Yii::log("Error al insertar en Api Call", CLogger::LEVEL_ERROR,"api");
            Yii::log("Error: ". $e->getMessage(), CLogger::LEVEL_ERROR,"api");
        }

    }

    private function sendError($error_message){

        $this->setApiCall($error_message,1);
        $arr = array(
            "error_code" => 1,
            "error_message" => $error_message,
        );
        print_r(json_encode($arr));
        Yii::app()->end();
    }

    private function sendOk($data,$is_simply=true){
        //is_simply es un flag para saber si solo viene un registro en el $data

        $this->setApiCall("N.A",0);
        if ($is_simply){
            $arr = array(
                "error_code" => 0,
                "data" => array($data),
            );
        }
        else{
            $arr = array(
                "error_code" => 0,
                "data" => $data,
            );
        }

        //print_r($data);
        print_r(json_encode($arr));
        //Yii::app()->end();
    }


    private function arrayToHTMLAttributes($aData_ = array()) {

        // Define un array temporal

        $aAttributes = array();

        // Recorre el array de entrada
        foreach ($aData_ as $sKey => $mValue_) {

            $aAttributes[] = $sKey . '=' . $mValue_;

        }

        // Une todos los elementos del aray temporal
        return ' ' . implode(' -- ', $aAttributes);

    }


} 