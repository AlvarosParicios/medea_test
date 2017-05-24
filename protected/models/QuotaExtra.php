<?php

Yii::import('application.models._base.BaseQuotaExtra');

class QuotaExtra extends BaseQuotaExtra
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function isActivated(){

		if ($this->isExpired()){
			$this->completion_date = date('Y-m-d H:i:s');
			$this->status = QuotaExtraStatus::EXPIRED;
			if (!$this->save()){
				Logger::log(CLogger::LEVEL_ERROR, "Error saving expired QUOTA EXTRA. Errors: " . var_export($this->attributes, true));
			}
			$extraHistory = new QuotaExtraHistory();
			$extraHistory->attributes = $this->attributes;
			$extraHistory->insertion_date = date('Y-m-d H:i:s');
			if (!$extraHistory->save()){
				Logger::log(CLogger::LEVEL_ERROR, "Error saving expired QUOTA EXTRA. Errors: " . var_export($this->attributes, true));
			}
			if ($this->delete()){
				Logger::log(CLogger::LEVEL_ERROR, "Current Quota Extra has EXPIRED. Recorded in historic successfully and DELETED.", true);
			}
			return false;
		}

		if (empty($this->activation_date)){
			return false;
		}

		if (strtotime($this->terminal0->getCurrentResetDate()) >= strtotime($this->activation_date)){

			$this->activation_date = null;
			$this->traffic_type = null;
			$this->initial_consumption = null;
			$this->save();
			return false;
		}

		return true;
	}

	public function activate($amountExceeded = 0, $uploadExceeded = false){

		//normalmente una cuota adicional se activa solo una vez, cuando se va a utilizar por primera vez,
		//pero puede darse el caso de que una cuota adicional se arrastre de un mes para el siguiente
		//en este caso, si es necesaria la cuota adicional, se mantiene el consumo, pero se reactiva y se cambia el
		//punto de inicio de consumo, para medir lo que se vaya consumiendo.

		//straight forward
		$this->activation_date = new DateTime();
		$this->activation_date = $this->activation_date->format('Y-m-d H:i:s');
		$this->traffic_type = $uploadExceeded ? QuotaExtraTrafficType::UPLOAD_TRAFFIC : QuotaExtraTrafficType::TOTAL_TRAFFIC;

		//aqui this->consumed_quota solo tiene valor si la cuota adicional viene del mes anterior
		$this->initial_consumption = $uploadExceeded ?
			$this->terminal0->upload_data_effective - ($amountExceeded + $this->consumed_quota) :
			$this->terminal0->total_data_effective - ($amountExceeded + $this->consumed_quota);

		$this->consumed_quota = ($amountExceeded + $this->consumed_quota);

		$this->status = QuotaExtraStatus::ACTIVATED;

		/*if (empty($this->expiration_date)){
			$this->expiration_date = $this->booking_date;
			$this->expiration_date->add(new DateInterval("P2M"));
			$this->expiration_date = $this->expiration_date->format('Y-m-d H:i:s');
		}*/

		return $this->save();
	}

	public function isExpired(){
		//return strtotime($this->expiration_date) < strtotime($this->activation_date);
        return strtotime("now") > strtotime($this->expiration_date);
	}

	public function isCompleted(){
		return $this->consumed_quota >= $this->quota;
	}


	public function save($runValidation=true,$attributes=null){

		if ($this->consumed_quota >= $this->quota){
			$this->completion_date = date('Y-m-d H:i:s');
			$this->status = QuotaExtraStatus::CONSUMED;
			$this->orderNotification(100);
			Logger::log(CLogger::LEVEL_WARNING, "Quota extra " . $this->extra_id . " is depleted. Setting completion date to: " . $this->completion_date . ". Ordering notification of 100% completed...");
		}
		return parent::save($runValidation, $attributes);
	}


	public function orderNotification($notification){
		$params = array(
			'sitId' => $this->terminal0->sit_id,
			'ispId' => $this->terminal0->isp_id,
			'value' => $notification,
			//'trafficType' => null,
			//'quota' => 'extra'
		);
		ActionPending::createNewAction($this->terminal0->sit_id, $this->terminal0->isp_id, ActionType::NOTIFY, $params);
		Logger::log(CLogger::LEVEL_INFO, "Ordering Notification. Params: " . var_export($params, true), false);
		$this->notification_total = $notification;
		if ($notification < 100){
			$this->save();
		}
	}
}