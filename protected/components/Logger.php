<?php

/**
 * Created by PhpStorm.
 * User: jtriana
 * Date: 30/09/2015
 * Time: 5:30 PM
 */
class Logger
{
    const CAT_QUOTA = "quota";
    const CAT_ARCHIVER = "archiver";
    const CAT_EXECUTOR = "executor";
    const CAT_RESETTER = "resetter";
    const CAT_APP = "application";
    const CAT_API = "api";

    public static function log($level, $message, $category = self::CAT_QUOTA, $onScreen = true){
        Yii::log($message, $level, $category);
        if ($onScreen){
            echo PHP_EOL . $message .PHP_EOL;
        }
    }

}