<?php

Yii::import('application.models._base.BaseActionPending');

class ActionPending extends BaseActionPending
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public static function createNewAction($sit_id = null, $isp_id = null, $actionType = null, $params = array()){
		$newAction = new ActionPending();
		$newAction->sit_id = $sit_id;
		$newAction->isp_id = $isp_id;
		$newAction->action_type = $actionType;
		$newAction->params = json_encode($params);
		$newAction->insertion_date = date('Y-m-d H:i:s');
		return $newAction->save();
	}

}