<?php

Yii::import('application.models._base.BaseQuotaProfile');

class QuotaProfile extends BaseQuotaProfile
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}