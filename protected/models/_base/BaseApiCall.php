<?php

/**
 * This is the model base class for the table "api_call".
 * DO NOT MODIFY THIS FILE! It is automatically generated by giix.
 * If any changes are necessary, you must set or override the required
 * property or method in class "ApiCall".
 *
 * Columns in table "api_call" available as properties of the model,
 * and there are no model relations.
 *
 * @property integer $id
 * @property string $api_name
 * @property string $date_call
 * @property string $user
 * @property string $query_param
 * @property string $status
 * @property string $return_message
 *
 */
abstract class BaseApiCall extends GxActiveRecord {

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function tableName() {
		return 'api_call';
	}

	public static function label($n = 1) {
		return Yii::t('app', 'ApiCall|ApiCalls', $n);
	}

	public static function representingColumn() {
		return 'api_name';
	}

	public function rules() {
		return array(
			array('api_name, date_call, user, query_param, status, return_message', 'required'),
			array('api_name, user', 'length', 'max'=>50),
			array('status', 'length', 'max'=>10),
			array('id, api_name, date_call, user, query_param, status, return_message', 'safe', 'on'=>'search'),
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
			'id' => Yii::t('app', 'ID'),
			'api_name' => Yii::t('app', 'Api Name'),
			'date_call' => Yii::t('app', 'Date Call'),
			'user' => Yii::t('app', 'User'),
			'query_param' => Yii::t('app', 'Query Param'),
			'status' => Yii::t('app', 'Status'),
			'return_message' => Yii::t('app', 'Return Message'),
		);
	}

	public function search() {
		$criteria = new CDbCriteria;

		$criteria->compare('id', $this->id);
		$criteria->compare('api_name', $this->api_name, true);
		$criteria->compare('date_call', $this->date_call, true);
		$criteria->compare('user', $this->user, true);
		$criteria->compare('query_param', $this->query_param, true);
		$criteria->compare('status', $this->status, true);
		$criteria->compare('return_message', $this->return_message, true);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}
}