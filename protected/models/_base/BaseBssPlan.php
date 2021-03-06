<?php

/**
 * This is the model base class for the table "bss_plan".
 * DO NOT MODIFY THIS FILE! It is automatically generated by giix.
 * If any changes are necessary, you must set or override the required
 * property or method in class "BssPlan".
 *
 * Columns in table "bss_plan" available as properties of the model,
 * followed by relations of table "bss_plan" available as properties of the model.
 *
 * @property integer $bss_plan_id
 * @property string $fus_service_id
 * @property integer $quota_profile
 * @property integer $fz
 * @property string $description
 *
 * @property FreeZone $fz0
 * @property QuotaProfile $quotaProfile
 * @property Terminal[] $terminals
 */
abstract class BaseBssPlan extends GxActiveRecord {

	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function tableName() {
		return 'bss_plan';
	}

	public static function label($n = 1) {
		return Yii::t('app', 'BssPlan|BssPlans', $n);
	}

	public static function representingColumn() {
		return 'fus_service_id';
	}

	public function rules() {
		return array(
			array('fus_service_id, quota_profile', 'required'),
			array('quota_profile, fz', 'numerical', 'integerOnly'=>true),
			array('fus_service_id', 'length', 'max'=>50),
			array('description', 'length', 'max'=>255),
			array('fz, description', 'default', 'setOnEmpty' => true, 'value' => null),
			array('bss_plan_id, fus_service_id, quota_profile, fz, description', 'safe', 'on'=>'search'),
		);
	}

	public function relations() {
		return array(
			'fz0' => array(self::BELONGS_TO, 'FreeZone', 'fz'),
			'quotaProfile' => array(self::BELONGS_TO, 'QuotaProfile', 'quota_profile'),
			'terminals' => array(self::HAS_MANY, 'Terminal', 'bss_plan'),
		);
	}

	public function pivotModels() {
		return array(
		);
	}

	public function attributeLabels() {
		return array(
			'bss_plan_id' => Yii::t('app', 'Bss Plan'),
			'fus_service_id' => Yii::t('app', 'Fus Service'),
			'quota_profile' => null,
			'fz' => null,
			'description' => Yii::t('app', 'Description'),
			'fz0' => null,
			'quotaProfile' => null,
			'terminals' => null,
		);
	}

	public function search() {
		$criteria = new CDbCriteria;

		$criteria->compare('bss_plan_id', $this->bss_plan_id);
		$criteria->compare('fus_service_id', $this->fus_service_id, true);
		$criteria->compare('quota_profile', $this->quota_profile);
		$criteria->compare('fz', $this->fz);
		$criteria->compare('description', $this->description, true);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}
}