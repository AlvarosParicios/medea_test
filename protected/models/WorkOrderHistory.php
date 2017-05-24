<?php

Yii::import('application.models._base.BaseWorkOrderHistory');

class WorkOrderHistory extends BaseWorkOrderHistory
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}