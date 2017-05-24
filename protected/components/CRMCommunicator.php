<?php
/**
 * Created by PhpStorm.
 * User: jtriana
 * Date: 18/09/15
 * Time: 11:19 AM
 *
 * This class is created because it's not enough with one Communicator... :P
 *
 */

class CRMCommunicator extends CApplicationComponent
{
    public static function CallRestAPI($url, $data = false, $method = "POST", $credentials = null){
        $curl = curl_init($url);

        switch ($method){
            case "POST":
                if ($data) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                }
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // Optional Authentication:
        //$credentials must be in the form "username:password"
        if (!empty($credentials)){
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($curl, CURLOPT_USERPWD, $credentials);
        }

        //Call
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }
}