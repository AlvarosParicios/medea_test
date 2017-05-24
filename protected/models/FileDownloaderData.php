<?php

Yii::import('application.models._base.BaseFileDownloaderData');

class FileDownloaderData extends BaseFileDownloaderData
{
	public $recordCount = 0;

	public function __construct(){
		parent::__construct();
		$this->recordCount = $this->count();
	}

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function checkForNewFile(){
		$numberOfRecords = $this->count();
		if ($this->recordCount != $numberOfRecords){
			$this->recordCount = $numberOfRecords;
			return true;
		}
		return false;
	}

}