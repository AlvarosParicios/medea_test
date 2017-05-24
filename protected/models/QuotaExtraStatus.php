<?php

Yii::import('application.models._base.BaseQuotaExtraStatus');

class QuotaExtraStatus extends BaseQuotaExtraStatus
{

	const BOOKED 		= 1;
	const ACTIVATED 	= 2;
	const CONSUMED 		= 3;
	const EXPIRED 		= 4;
	const CANCELLED 	= 5;
    const DECOMMISSED   = 6;


	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}