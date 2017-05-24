<?php

Yii::import('application.models._base.BaseFileDownloaderStatus');

class FileDownloaderStatus extends BaseFileDownloaderStatus
{
	//file downloader data statuses
	const DOWNLOAD_OK = 1;
    const DOWNLOAD_ERROR = 2;
    const SKIPPED = 3;
	const PROCESSED_OK = 4;
	const PROCESSED_ERROR = 5;
    const PENDING = 6;
	const QUEUED = 7;

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}
}