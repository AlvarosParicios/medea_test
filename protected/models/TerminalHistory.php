<?php

Yii::import('application.models._base.BaseTerminalHistory');

class TerminalHistory extends BaseTerminalHistory
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}