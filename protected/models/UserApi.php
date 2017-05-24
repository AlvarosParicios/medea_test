<?php

Yii::import('application.models._base.BaseUserApi');

class UserApi extends BaseUserApi
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}