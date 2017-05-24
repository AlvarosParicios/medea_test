<?php

Yii::import('application.models._base.BaseActionType');

class ActionType extends BaseActionType
{
	const CHANGE_SLA = 1;
	const RESET_TERMINAL = 2;
    const NOTIFY = 3;

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}