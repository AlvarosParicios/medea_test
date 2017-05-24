<?php

Yii::import('application.models._base.BaseTerminalSlaStatus');

class TerminalSlaStatus extends BaseTerminalSlaStatus
{
    const NOMINAL = 1;
    const UPLOAD_EXCEEDED = 2;
    const TOTAL_EXCEEDED = 3;

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}