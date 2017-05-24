<?php

Yii::import('application.models._base.BaseActionHistory');

class ActionHistory extends BaseActionHistory
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function save($runValidation=true,$attributes=null){
		if ($this->retries === null){
			$this->retries = 0;
		}
		return parent::save($runValidation, $attributes);
	}
}