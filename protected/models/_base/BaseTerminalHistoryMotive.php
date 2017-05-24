<?php

/**
 * This is the model base class for the table "terminal_history_motive".
 * DO NOT MODIFY THIS FILE! It is automatically generated by giix.
 * If any changes are necessary, you must set or override the required
 * property or method in class "TerminalHistoryMotive".
 *
 * Columns in table "terminal_history_motive" available as properties of the model,
 * followed by relations of table "terminal_history_motive" available as properties of the model.
 *
 * @property integer $id
 * @property string $literal
 *
 * @property TerminalHistory[] $terminalHistories
 */
abstract class BaseTerminalHistoryMotive extends GxActiveRecord {

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function tableName() {
		return 'terminal_history_motive';
	}

	public static function label($n = 1) {
		return Yii::t('app', 'TerminalHistoryMotive|TerminalHistoryMotives', $n);
	}

	public static function representingColumn() {
		return 'literal';
	}

	public function rules() {
		return array(
			array('literal', 'required'),
			array('literal', 'length', 'max'=>255),
			array('id, literal', 'safe', 'on'=>'search'),
		);
	}

	public function relations() {
		return array(
			'terminalHistories' => array(self::HAS_MANY, 'TerminalHistory', 'motive'),
		);
	}

	public function pivotModels() {
		return array(
		);
	}

	public function attributeLabels() {
		return array(
			'id' => Yii::t('app', 'ID'),
			'literal' => Yii::t('app', 'Literal'),
			'terminalHistories' => null,
		);
	}

	public function search() {
		$criteria = new CDbCriteria;

		$criteria->compare('id', $this->id);
		$criteria->compare('literal', $this->literal, true);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}
}