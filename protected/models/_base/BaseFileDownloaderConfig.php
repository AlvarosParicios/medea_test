<?php

/**
 * This is the model base class for the table "file_downloader_config".
 * DO NOT MODIFY THIS FILE! It is automatically generated by giix.
 * If any changes are necessary, you must set or override the required
 * property or method in class "FileDownloaderConfig".
 *
 * Columns in table "file_downloader_config" available as properties of the model,
 * and there are no model relations.
 *
 * @property string $varname
 * @property string $value
 * @property string $applicable
 * @property string $applicable_date
 * @property integer $id
 *
 */
abstract class BaseFileDownloaderConfig extends GxActiveRecord {

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function tableName() {
		return 'file_downloader_config';
	}

	public static function label($n = 1) {
		return Yii::t('app', 'FileDownloaderConfig|FileDownloaderConfigs', $n);
	}

	public static function representingColumn() {
		return 'varname';
	}

	public function rules() {
		return array(
			array('varname, value, applicable', 'required'),
			array('varname, value, applicable', 'length', 'max'=>255),
			array('applicable_date', 'safe'),
			array('applicable_date', 'default', 'setOnEmpty' => true, 'value' => null),
			array('varname, value, applicable, applicable_date, id', 'safe', 'on'=>'search'),
		);
	}

	public function relations() {
		return array(
		);
	}

	public function pivotModels() {
		return array(
		);
	}

	public function attributeLabels() {
		return array(
			'varname' => Yii::t('app', 'Varname'),
			'value' => Yii::t('app', 'Value'),
			'applicable' => Yii::t('app', 'Applicable'),
			'applicable_date' => Yii::t('app', 'Applicable Date'),
			'id' => Yii::t('app', 'ID'),
		);
	}

	public function search() {
		$criteria = new CDbCriteria;

		$criteria->compare('varname', $this->varname, true);
		$criteria->compare('value', $this->value, true);
		$criteria->compare('applicable', $this->applicable, true);
		$criteria->compare('applicable_date', $this->applicable_date, true);
		$criteria->compare('id', $this->id);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}
}