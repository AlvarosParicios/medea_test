<?php

Yii::import('application.models._base.BaseTerminalHistoryMotive');

class TerminalHistoryMotive extends BaseTerminalHistoryMotive
{

	const HOURLY_BACKUP = 1;
	const CYCLE_RESET = 2;
	const TERMINAL_MODIFICATION = 3;
    const DECOMMISSED_TERMINAL = 4;
    const REGISTERED_TERMINAL = 5;


	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}