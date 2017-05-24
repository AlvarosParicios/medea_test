<?php

/**
 * This is the model base class for the table "file_downloader_data".
 * DO NOT MODIFY THIS FILE! It is automatically generated by giix.
 * If any changes are necessary, you must set or override the required
 * property or method in class "FileDownloaderData".
 *
 * Columns in table "file_downloader_data" available as properties of the model,
 * followed by relations of table "file_downloader_data" available as properties of the model.
 *
 * @property string $filename
 * @property string $full_path_file
 * @property string $insertion_date
 * @property string $downloaded_date
 * @property string $processing_date
 * @property integer $status
 * @property integer $retry
 * @property integer $id
 * @property integer $isp_id
 *
 * @property FileDownloaderStatus $status0
 */
abstract class BaseFileDownloaderData extends GxActiveRecord {

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function tableName() {
		return 'file_downloader_data';
	}

	public static function label($n = 1) {
		return Yii::t('app', 'FileDownloaderData|FileDownloaderDatas', $n);
	}

	public static function representingColumn() {
		return 'filename';
	}

	public function rules() {
		return array(
			array('filename, full_path_file, isp_id', 'required'),
			array('status, retry, isp_id', 'numerical', 'integerOnly'=>true),
			array('filename, full_path_file', 'length', 'max'=>255),
			array('insertion_date, downloaded_date, processing_date', 'safe'),
			array('insertion_date, downloaded_date, processing_date, status, retry', 'default', 'setOnEmpty' => true, 'value' => null),
			array('filename, full_path_file, insertion_date, downloaded_date, processing_date, status, retry, id, isp_id', 'safe', 'on'=>'search'),
		);
	}

	public function relations() {
		return array(
			'status0' => array(self::BELONGS_TO, 'FileDownloaderStatus', 'status'),
		);
	}

	public function pivotModels() {
		return array(
		);
	}

	public function attributeLabels() {
		return array(
			'filename' => Yii::t('app', 'Filename'),
			'full_path_file' => Yii::t('app', 'Full Path File'),
			'insertion_date' => Yii::t('app', 'Insertion Date'),
			'downloaded_date' => Yii::t('app', 'Downloaded Date'),
			'processing_date' => Yii::t('app', 'Processing Date'),
			'status' => null,
			'retry' => Yii::t('app', 'Retry'),
			'id' => Yii::t('app', 'ID'),
			'isp_id' => Yii::t('app', 'Isp'),
			'status0' => null,
		);
	}

	public function search() {
		$criteria = new CDbCriteria;

		$criteria->compare('filename', $this->filename, true);
		$criteria->compare('full_path_file', $this->full_path_file, true);
		$criteria->compare('insertion_date', $this->insertion_date, true);
		$criteria->compare('downloaded_date', $this->downloaded_date, true);
		$criteria->compare('processing_date', $this->processing_date, true);
		$criteria->compare('status', $this->status);
		$criteria->compare('retry', $this->retry);
		$criteria->compare('id', $this->id);
		$criteria->compare('isp_id', $this->isp_id);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}
}