<?php
/**
 * Created by PhpStorm.
 * User: dalvaro
 * Date: 24/02/16
 * Time: 12:49
 */
class DaniController extends GxController{

    public function actionIndex()
    {
        try{
            //Obtain all action pending for the current terminal ($sit_id, isp_id)
            $sit_id = 5;
            $isp_id = 55;
            $criteria = new CDbCriteria();
            $criteria->condition='sit_id= :sit_id AND isp_id= :isp_id ';
            $criteria->params=array(':sit_id'=>$sit_id, ':isp_id'=>$isp_id);
            $actions_pending = ActionPending::model()->findAll($criteria);
            if(!$actions_pending){
                echo "No existen  acciones pendientes para este terminal";
                Yii::log('No existen  acciones pendientes para este terminal --> sit_id:'.$sit_id.' -- isp_id:'.$isp_id,CLogger::LEVEL_ERROR,"api");
            }
            else{
                $transaction = Yii::app()->db->beginTransaction();
                //echo "<pre>";
                //print_r($actions_pending);
                //echo "</pre>";
                //exit;
                foreach($actions_pending as $action_pending){

                    //Ahora generamos la action pending historica
                    $action_history = new ActionHistory();
                    $action_history->attributes = $action_pending->attributes;
                    $action_history->info_error = "CANCELADA POR BAJA DE TERMINAL";
                    $action_history->action_completion_date = date('Y-m-d H:i:s');
                    //print_r($action_history);

                    try{
                        if(!$action_pending->delete()){
                            $error_model = $action_pending->getErrors();
                            print_r('Error removing action_pending -> id: '.$action_pending->action_id);
                            echo "<br>";
                            print_r('You must delete action pending manually... ');
                            echo "<br>";
                            Yii::log('Error removing action_pending -> id: '.$action_pending->action_id,CLogger::LEVEL_TRACE,"api");
                            $transaction->rollback();
                            //$this->sendError('Error en BD, Terminal');
                            break;
                            //continue;
                        }
                        if($action_history->save()){
                            print_r('Saved Action Pending in Action Pending History');
                            echo "<br>";
                            Yii::log('Saved Action Pending in Quota Extra history',CLogger::LEVEL_TRACE,"api");

                        }
                        else{
                            $error_model = $action_history->getErrors();
                            print_r('Could not move Action Pending in Action Pending History -> id: '.$action_pending->action_id);
                            echo "<br>";
                            print_r($error_model);
                            var_dump($error_model);
                            Yii::log('Could not move Action Pending in Action Pending History', CLogger::LEVEL_ERROR,"api");
                            Yii::log(var_export($error_model, true), CLogger::LEVEL_ERROR,"api");
                            //$transaction->rollback();
                            //$this->sendError('DATABASE_ERROR -> '.$error_model);
                            //$this->sendError('DATABASE_ERROR');
                            break;

                        }
                        print_r('Decommissed Action Pending con ID '.$action_pending->action_id);
                        echo "<br>";
                        Yii::log('Decommissed Action Pending con ID '.$action_pending->action_id,CLogger::LEVEL_TRACE,"api");


                    }
                    catch(Exception $e){
                        echo "1111";
                        echo "<br>";
                        print_r($e->getMessage());
                        Yii::log('BD PROBLEM', CLogger::LEVEL_ERROR,"api");
                        Yii::log(var_export($e->getMessage(), true), CLogger::LEVEL_ERROR,"api");
                        $transaction->rollback();
                        //$this->sendError('DATABASE_ERROR -> '.$e->getMessage());
                        //$this->sendError('DATABASE_ERROR');
                        break;
                    }
                }
                $transaction->commit();
            }

        }
        catch(Exception $e){
            echo "2222";
            echo "<br>";
            print_r($e->getMessage());

        }

    }
}