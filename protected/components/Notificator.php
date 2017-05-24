<?php

/**
 * Created by PhpStorm.
 * User: jtriana
 * Date: 29/09/2015
 * Time: 5:15 PM
 */
class Notificator
{

    const UPLOAD = "notification_upload";
    const TOTAL = "notification_total";

    public static function notify($object, $notificationThresholds = array(100)){

        $objectClass = get_class($object);
        if ($objectClass == "Terminal"){
            $uploadQuota = $object->bssPlan->quotaProfile->upload_quota;
            $totalQuota = $object->bssPlan->quotaProfile->total_quota;
            $currentTotalConsumption = $object->total_data_effective;
        }else if ($objectClass == "QuotaExtra"){
            $uploadQuota = 0;
            $totalQuota = $object->quota;
            $currentTotalConsumption = $object->consumed_quota;
        }


        if($uploadQuota != 0){
            $currentUploadConsumption = $object->upload_data_effective;
            foreach($notificationThresholds as $notificationPoint){
                if($currentUploadConsumption >= $uploadQuota * $notificationPoint / 100){
                    if ($object->notification_upload < $notificationPoint){
                        Logger::log(CLogger::LEVEL_WARNING, "Notificating, UPLOAD quota passed the " . $notificationPoint . "%");
                        $object->orderNotification($notificationPoint, self::UPLOAD);
                    }
                }
            }
        }

        foreach($notificationThresholds as $notificationPoint){
            if($currentTotalConsumption >= $totalQuota * $notificationPoint / 100){
                if ($object->notification_total < $notificationPoint){
                    Logger::log(CLogger::LEVEL_WARNING, "Notificating, TOTAL quota passed the " . $notificationPoint . "%");
                    $object->orderNotification($notificationPoint);
                }
            }
        }
    }
}