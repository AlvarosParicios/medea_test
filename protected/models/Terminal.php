<?php

Yii::import('application.models._base.BaseTerminal');

class Terminal extends BaseTerminal
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

	public function save(){
		$this->last_update_date = date('Y-m-d H:i:s');
		return parent::save();
	}

	public function checkQuota($verbose = false){

		//se comprueba si se ha alcanzado el 80% o el 100% de cuota, tanto de subida como del total
		Notificator::notify($this, Yii::app()->params['notificationThresholds']);

		//==================

		//si se pasa del total de cuota chequea si hay cuota extra.
		//En caso de que haya, chequea que no esté agotada y si es la primera vez que se usa la activa
		//en caso de que no haya o esté agotada, ordena el cambio de velocidad y cambia el estado
		//similar para upload
		//si después de pasarse se contratase la cuota extra, hay que poner el estado de nuevo en nominal

		$extra = $this->getExtraQuota();

		if ($this->sla_status_id != TerminalSlaStatus::TOTAL_EXCEEDED && $this->isTotalQuotaExceeded()){

			//extra quota checking
			if (empty($extra) || $extra->isCompleted()){

				//quota exceeded
				Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $this->sit_id . " ISP: " . $this->isp_id . " exceeded its total quota. Changing status to TOTAL QUOTA EXCEEDED", $verbose);
				$this->sla_status_id = TerminalSlaStatus::TOTAL_EXCEEDED;
				$sla = $this->bssPlan->quotaProfile->sla_total_exceded;
				$this->orderActionChangeSLA($sla, $verbose);
                $this->save();

			}else{

				if (!$extra->isActivated()){
					Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $this->sit_id . " ISP: " . $this->isp_id . " exceeded its total quota. Activating EXTRA QUOTA.", $verbose);
                    //De momento no se considera la cuota excedida para activar la cuota (nio en subida ni en total)
					//$extra->activate($this->getTotalQuotaExceeded());
                    $extra->activate();
				}
				//la notificación del 100% de cuota extra se hace cuando esta se guarda, comprueba si está completa
				Notificator::notify($extra, array(80));
			}

		}else if ($this->sla_status_id != TerminalSlaStatus::TOTAL_EXCEEDED && $this->sla_status_id != TerminalSlaStatus::UPLOAD_EXCEEDED && $this->isUploadQuotaExceeded()){

            if (empty($extra) || $extra->isCompleted()){

				Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $this->sit_id . " ISP: " . $this->isp_id . " exceeded its upload quota. Changing status to UPLOAD QUOTA EXCEEDED", $verbose);
				$this->sla_status_id = TerminalSlaStatus::UPLOAD_EXCEEDED;
				$sla = $this->bssPlan->quotaProfile->sla_upload_exceded;
				$this->orderActionChangeSLA($sla, $verbose);
                $this->save();

			}else{

				if (!$extra->isActivated()){

					Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $this->sit_id . " ISP: " . $this->isp_id . " exceeded its upload quota. Activating EXTRA QUOTA.", $verbose);
                    //De momento no se considera la cuota excedida para activar la cuota (nio en subida ni en total)
					//$extra->activate($this->getUploadQuotaExceeded(), true);
                    $extra->activate(0, true);
				}
				//la notificación del 100% de cuota extra se hace cuando esta se guarda, comprueba si está completa
				Notificator::notify($extra, array(80));
			}

		//en caso de que se contrate cuota extra después de haber excedido las cuotas nominales o se amplíe el plan
		}else if (($this->sla_status_id == TerminalSlaStatus::TOTAL_EXCEEDED || $this->sla_status_id == TerminalSlaStatus::UPLOAD_EXCEEDED)
			&& (!empty($extra) || !$this->isTotalQuotaExceeded())){

			//si se contrató una cuota extra
			if (!empty($extra)){

				if (!$extra->isActivated()){
					if ($this->sla_status_id == TerminalSlaStatus::TOTAL_EXCEEDED){
						Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $this->sit_id . " ISP: " . $this->isp_id . " has unused extra quota. Activating Total Extra Quota...", $verbose);
						$extra->activate();
					}else{
						Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $this->sit_id . " ISP: " . $this->isp_id . " has unused extra quota. Activating Upload Extra Quota...", $verbose);
						$extra->activate(0,true);
					}
				}

				$this->sla_status_id = TerminalSlaStatus::NOMINAL;
				$sla = $this->bssPlan->quotaProfile->sla_nominal;
				Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $this->sit_id . " ISP: " . $this->isp_id . " has unused extra quota. Restoring NOMINAL Status.", $verbose);

			//si se amplió el plan y hay cuota libre de subida (caso de reseteo de cuota también)
			}else if(!$this->isUploadQuotaExceeded()){

				$this->sla_status_id = TerminalSlaStatus::NOMINAL;
				$this->notification_total = 0;
				$this->notification_upload = 0;
				$sla = $this->bssPlan->quotaProfile->sla_nominal;
				Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $this->sit_id . " ISP: " . $this->isp_id . " has available quota. Restoring NOMINAL Status.", $verbose);

			//si se amplió el plan y hay cuota libre total pero no de subida
			}else if (($this->sla_status_id == TerminalSlaStatus::TOTAL_EXCEEDED || $this->isUploadQuotaExceeded()) && $this->sla_status_id != TerminalSlaStatus::UPLOAD_EXCEEDED) {

				$this->sla_status_id = TerminalSlaStatus::UPLOAD_EXCEEDED;
				$this->notification_total = 0;
				$sla = $this->bssPlan->quotaProfile->sla_upload_exceded;
				Logger::log(CLogger::LEVEL_WARNING, "Terminal: " . $this->sit_id . " ISP: " . $this->isp_id . " has available download quota. Restoring UPLOAD EXCEEDED Status.", $verbose);

			}

			if (isset($sla)){
				$this->orderActionChangeSLA($sla, $verbose);
				$this->save();
			}
		}
	}


	private function orderActionChangeSLA($newSLA, $verbose = false){
		$params = array(
			'sit_id' => $this->sit_id,
			'isp_id' => $this->isp_id,
			'sla' => $newSLA
		);
		ActionPending::createNewAction($this->sit_id, $this->isp_id, ActionType::CHANGE_SLA, $params);
		Logger::log(CLogger::LEVEL_INFO, "Ordering new SLA Change. Params: " . var_export($params, true), $verbose);
	}

	public function orderNotification($notification, $type = Notificator::TOTAL, $verbose = false){
		$trafficTypeArray = explode("_", $type);
        $params = array(
			'sitId' => $this->sit_id,
			'ispId' => $this->isp_id,
			//this is done here because $type is an attribute of terminal
			'value' => $notification,
			//'quota' => 'terminal'
			//'trafficType' => $trafficTypeArray[1],
		);
		ActionPending::createNewAction($this->sit_id, $this->isp_id, ActionType::NOTIFY, $params);
		Logger::log(CLogger::LEVEL_INFO, "Ordering Notification " . $type . " Params: " . var_export($params, true), $verbose);
		$this->$type = $notification;
		$this->save();
	}

	public function isUploadQuotaExceeded(){
        if($this->bssPlan->quotaProfile->upload_quota == 0){
            return false;
        }else{
			// a lo sumo, eff = total, así que es seguro hacer min(eff, total) >= upload_quota
            return min($this->upload_data_effective, $this->upload_data_real) >= $this->bssPlan->quotaProfile->upload_quota;
        }
	}

	public function isTotalQuotaExceeded(){
		return min($this->total_data_effective, $this->total_data_real) >= $this->bssPlan->quotaProfile->total_quota;
	}

	public function hasExtraQuota(){
		return count($this->quotaExtras);
	}

	public function getExtraQuotaByIndex($index = 0){
		return $this->quotaExtras[$index];
	}

	public function getExtraQuota(){
		if ($this->hasExtraQuota()){
			foreach($this->quotaExtras as $extra){
				if (empty($extra->completion_date)){
					return $extra;
				}
			}
		}
		return false;
	}

	public function getUploadQuotaExceeded(){
		return $this->getQuotaExceeded(false);
	}

	public function getTotalQuotaExceeded(){
		return $this->getQuotaExceeded();
	}

	public function getCurrentResetDate(){

		return date('d') < $this->reset_day ? date('Y') . '-' . (date('m') - 1) . '-' . $this->reset_day : date('Y') . '-' . date('m') . '-' . $this->reset_day;
	}

	private function getQuotaExceeded($isTotal = true){

		$exceededQuota = 0;
		$criteria = new CDbCriteria();
		$criteria->addCondition('terminal = ' . $this->terminal_id);
		$criteria->addCondition('completion_date IS NOT NULL');
		//$criteria->addCondition('expiration_date > NOW()');
		$criteria->order = 'completion_date DESC';
		$criteria->limit = 1;
		$last_consumed_extra = QuotaExtra::model()->find($criteria);

		if ($isTotal){
			$exceededQuota = empty($last_consumed_extra) ? $this->total_data_effective - $this->bssPlan->quotaProfile->total_quota : $last_consumed_extra->consumed_quota - $last_consumed_extra->quota;
		}else{
			$exceededQuota = empty($last_consumed_extra) ? $this->upload_data_effective - $this->bssPlan->quotaProfile->upload_quota : $last_consumed_extra->consumed_quota - $last_consumed_extra->quota;
		}
		return $exceededQuota;
	}
}