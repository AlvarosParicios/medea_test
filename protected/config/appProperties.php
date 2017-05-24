<?php

return array(

    //HUB Communicator Properties
    //Connection data to HUB API
    'serviceURLBase' => 'https://10.193.193.13/nbi/',
    'serviceLogin' => 'Neutra',
    'servicePassword' => 'N3utR4',

    //Resetter Properties
    //Retries for reset attempts
    'resetterRetries' => 5,

    //CRM Comm Properties
    //notification settings (CRM API)
    'notificationThresholds' => array(80, 100),
    'notificationUrl' => 'https://www.quantis.es/jsonservices_fus/sendSMSQuota',
    'notificationAuth' => array(
        'user' => 'Qmedea',
        'password' => '78yuF64266yevG89'
    ),

    //Executor Properties
    //executor retries
    'executorRetries' => 10,
    //executor waiting time before spanning a new process (in seconds)
    'executorWaitSeconds' => 3,
);

?>