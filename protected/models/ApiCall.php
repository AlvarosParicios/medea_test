<?php

Yii::import('application.models._base.BaseApiCall');

class ApiCall extends BaseApiCall
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}