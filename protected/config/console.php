<?php

// This is the configuration for yiic console application.
// Any writable CConsoleApplication properties can be configured here.
return array(
    'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
    'name'=>'Medea Console App',

    // preloading 'log' component
    'preload'=>array('log'),

    // autoloading model and component classes
    'import'=>array(
        'application.models.*',
        'application.components.*',
        'ext.giix-components.*', // giix components
    ),

    // application components
    'components'=>array(

        // database settings are configured in database.php
        'db'=>require(dirname(__FILE__).'/database.php'),

        'log'=>array(
            'class'=>'CLogRouter',
            'routes'=>array(
                array(
                    'class'=>'CPSLiveLogRoute',
                    'logFile' => 'console.log',
                    'levels'=>'error, warning',
                ),
                array(
                    'class' => 'CPSLiveLogRoute',
                    'levels' => 'error, warning, info, trace',
                    //file size in KB
                    'maxFileSize' => '25600',
                    'logFile' => 'quota.log',
                    //rotations
                    //'maxLogFiles' => 10,
                    //  Optional excluded category
                    /*'excludeCategories' => array(
                        'system.db.CDbCommand',
                    ),*/
                    'categories'=>'quota'
                ),
                array(
                    'class'=>'CPSLiveLogRoute',
                    'maxFileSize' => '2048',
                    'logFile' => 'archiver.log',
                    'levels'=>'error,warning,info,trace',
                    'categories'=>'archiver'
                ),
                array(
                    'class'=>'CPSLiveLogRoute',
                    'maxFileSize' => '2048',
                    'logFile' => 'executor.log',
                    'levels'=>'error,warning,info,trace',
                    'categories'=>'executor'
                ),
                array(
                    'class'=>'CPSLiveLogRoute',
                    'maxFileSize' => '2048',
                    'logFile' => 'resetter.log',
                    'levels'=>'error,warning,info,trace',
                    'categories'=>'resetter'
                ),
                array(
                    'class'=>'CPSLiveLogRoute',
                    'maxFileSize' => '2048',
                    'logFile' => 'filedown.log',
                    'levels'=>'error,warning,info,trace',
                    'categories'=>'filedown'
                ),
            ),
        ),

    ),

    // application-level parameters that can be accessed
    // using Yii::app()->params['paramName']
    'params'=>require(dirname(__FILE__).'/appProperties.php'),
);
