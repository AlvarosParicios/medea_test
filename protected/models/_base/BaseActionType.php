<?php

/**
 * This is the model base class for the table "action_type".
 * DO NOT MODIFY THIS FILE! It is automatically generated by giix.
 * If any changes are necessary, you must set or override the required
 * property or method in class "ActionType".
 *
 * Columns in table "action_type" available as properties of the model,
 * followed by relations of table "action_type" available as properties of the model.
 *
 * @property integer $action_type_id
 * @property string $function
 *
 * @property ActionHistory[] $actionHistories
 * @property ActionPending[] $actionPendings
 */
abstract class BaseActionType extends GxActiveRecord {

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function tableName() {
		return 'action_type';
	}

	public static function label($n = 1) {
		return Yii::t('app', 'ActionType|ActionTypes', $n);
	}

	public static function representingColumn() {
		return 'function';
	}

	public function rules() {
		return array(
			array('function', 'required'),
			array('function', 'length', 'max'=>255),
			array('action_type_id, function', 'safe', 'on'=>'search'),
		);
	}

	public function relations() {
		return array(
			'actionHistories' => array(self::HAS_MANY, 'ActionHistory', 'action_type'),
			'actionPendings' => array(self::HAS_MANY, 'ActionPending', 'action_type'),
		);
	}

	public function pivotModels() {
		return array(
		);
	}

	public function attributeLabels() {
		return array(
			'action_type_id' => Yii::t('app', 'Action Type'),
			'function' => Yii::t('app', 'Function'),
			'actionHistories' => null,
			'actionPendings' => null,
		);
	}

	public function search() {
		$criteria = new CDbCriteria;

		$criteria->compare('action_type_id', $this->action_type_id);
		$criteria->compare('function', $this->function, true);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}
}