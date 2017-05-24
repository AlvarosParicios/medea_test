<?php

Yii::import('application.models._base.BaseFileDownloaderConfig');

class FileDownloaderConfig extends BaseFileDownloaderConfig
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}