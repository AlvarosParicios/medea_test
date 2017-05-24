<?php

Yii::import('application.models._base.BaseFreeZone');

class FreeZone extends BaseFreeZone
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function isFreeZoneActive($currentTime = null){
		if (empty($currentTime)) $currentTime = new DateTime();
		$initDate = $this->getInitDate();
		$finalDate = $this->getFinalDate();

		if ($initDate >= $finalDate){
			if (date('a') == 'pm'){
				$finalDate = $finalDate->add(new DateInterval('P1D'));
			}else{
				$initDate = $initDate->sub(new DateInterval('P1D'));
			}
		}
		Logger::log(CLogger::LEVEL_INFO, "Freezone boundries: Init date: " . $initDate->format("Y-m-d H:i:s") . " End date: " . $finalDate->format("Y-m-d H:i:s"));
		return $initDate <= $currentTime && $currentTime <= $finalDate;
	}

	public function getInitDate(){
		$min = is_null($this->initial_min) ? date('i') : $this->initial_min;
		$hour = is_null($this->initial_hour) ? date('H') : $this->initial_hour;
		$day = empty($this->initial_dom) ? date('d') : $this->initial_dom;
		$month = empty($this->initial_month) ? date('m') : $this->initial_month;
		$date = date('Y') . '-' . $month . '-' . $day . ' ' . $hour . ':' . $min;
		return new DateTime($date);
	}

	public function getFinalDate(){
		$min = is_null($this->final_min) ? date('i') : $this->final_min;
		$hour = is_null($this->final_hour) ? date('H') : $this->final_hour;
		$day = empty($this->final_dom) ? date('d') : $this->final_dom;
		$month = empty($this->final_month) ? date('m') : $this->final_month;
		$date = date('Y') . '-' . $month . '-' . $day . ' ' . $hour . ':' . $min;
		return new DateTime($date);
	}
}