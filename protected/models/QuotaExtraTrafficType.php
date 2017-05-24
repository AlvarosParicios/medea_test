<?php

Yii::import('application.models._base.BaseQuotaExtraTrafficType');

class QuotaExtraTrafficType extends BaseQuotaExtraTrafficType
{
	//my traffic types
	const UPLOAD_TRAFFIC = 1;
	const TOTAL_TRAFFIC = 2;

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}