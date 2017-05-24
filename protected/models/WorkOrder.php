<?php

Yii::import('application.models._base.BaseWorkOrder');

class WorkOrder extends BaseWorkOrder
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}