<?php
/**
 * Created by PhpStorm.
 * User: drinconada
 * Date: 18/09/15
 * Time: 11:19 AM
 */

class Communicator extends CApplicationComponent
{


    /**
     * Resets the Accumulated Forward Volume of a given terminal
     *
     * @param $terminal_isp_id
     * @param $terminal_sit_id
     * @return bool
     * @throws Exception
     * @throws SoapFault
     */
    public static function resetAccumulatedForwardVolume($terminal_isp_id, $terminal_sit_id)
    {
        $wsdl = 'performance/2.2/PerformanceInterface';
        $function = 'resetAccumulatedForwardVolume';

        $function_params = array();
        $function_params[0]['ispId'] = $terminal_isp_id;
        $function_params[0]['sitId'] = $terminal_sit_id;

        try {
            $result = self::execute($wsdl, $function, $function_params);
        } catch (SoapFault $e) {
            throw $e;
        }
        return $result;
    }


    /**
     * Resets the Accumulated Return Volume of a given terminal
     *
     * @param $terminal_isp_id
     * @param $terminal_sit_id
     * @return bool
     * @throws Exception
     * @throws SoapFault
     */
    public static function resetAccumulatedReturnVolume($terminal_isp_id, $terminal_sit_id)
    {
        $wsdl = 'performance/2.2/PerformanceInterface';
        $function = 'resetAccumulatedReturnVolume';

        $function_params = array();
        $function_params[0]['ispId'] = $terminal_isp_id;
        $function_params[0]['sitId'] = $terminal_sit_id;

        try {
            $result = self::execute($wsdl, $function, $function_params);
        } catch (SoapFault $e) {
            throw $e;
        }
        return $result;
    }


    /**
     * Changes SLA of a given terminal
     *
     * @param $terminal_isp_id
     * @param $terminal_sit_id
     * @param $new_sla_id
     * @return bool
     * @throws Exception
     * @throws SoapFault
     */
    public static function changeSLA($terminal_isp_id, $terminal_sit_id, $new_sla_id)
    {
        $wsdl = 'terminal/2.2/TerminalInterface';
        $function = 'updateTerminal';

        $function_params = array();
        $function_params['terminalParameters']['ispId'] = $terminal_isp_id;
        $function_params['terminalParameters']['sitId'] = $terminal_sit_id;
        $function_params['terminalParameters']['slaId'] = $new_sla_id;

        try {
            $result = self::execute($wsdl, $function, $function_params);
        } catch (SoapFault $e) {
            throw $e;
        }
        return $result;
    }


    /**
     * Finds a terminal given its ispId and its sitId
     *
     * @param $terminal_isp_id
     * @param $terminal_sit_id
     * @return bool None if it doesn't exist or information about the terminal
     * @throws Exception
     * @throws SoapFault
     */
    public static function findTerminal($terminal_isp_id, $terminal_sit_id)
    {
        $wsdl = "terminal/2.2/TerminalInterface?wsdl";
        $function = "findTerminal";

        $function_params = array();
        $function_params[0]['ispId'] = $terminal_isp_id;
        $function_params[0]['sitId'] = $terminal_sit_id;

        try {
            $result = self::execute($wsdl, $function, $function_params);
        } catch (SoapFault $e) {
            throw $e;
        }
        return $result;
    }


    /**
     * Finds all SLA Ids for a given ISP Id
     *
     * @param $terminal_isp_id
     * @return bool
     * @throws Exception
     * @throws SoapFault
     */
    public static function findAllSLAIdsForISP($terminal_isp_id)
    {
        $wsdl = 'terminal/2.2/TerminalInterface?wsdl';
        $function = 'findSlaIdsForServiceProvider';
        $function_params = array();
        $function_params[0]['ispId'] = $terminal_isp_id;

        try {
            $result = self::execute($wsdl, $function, $function_params);
        } catch (SoapFault $e) {
            throw $e;
        }
        return $result;
    }


    /**
     * Creates WSDL client and executes function
     *
     * @param string $wsdl_name
     * @param string $function_name
     * @param array $function_params
     * @return bool
     * @throws Exception
     * @throws SoapFault
     */
    private static function execute($wsdl_name, $function_name, $function_params)
    {
        $serviceURL = Yii::app()->params['serviceURLBase'] . $wsdl_name . "?wsdl";
        $client_params = array(
            'login' => Yii::app()->params['serviceLogin'],
            'password' => Yii::app()->params['servicePassword'],
            'exceptions' => true);

        //Sets error handler and transforms to SoapFault exception
        set_error_handler('Communicator::handleSoapClientPhpErrors');

        //Create client and call function
        try {
            $client = new SoapClient($serviceURL, $client_params);
            $result = $client->__soapCall($function_name, array('parameters' => $function_params));
        } catch (SoapFault $e) {
            //var_dump(libxml_get_last_error());
            //echo "<BR><BR>";
            //var_dump($e);
            throw $e;
        }
        return $result;
    }


    /**
     * Transforms PHPErrors related to SoapClient to exceptions in order to catch and manage
     *
     * @param $errno
     * @param $errmsg
     * @param $filename
     * @param $linenum
     * @param $vars
     */
    public static function handleSoapClientPhpErrors($errno, $errmsg, $filename, $linenum, $vars)
    {
        if (stristr($errmsg, "SoapClient::SoapClient")) {
            error_log($errmsg); // silently log error
            return; // skip error handling
        }
    }

    public static function notifyBSS($sit_id, $isp_id, $notification){
        //todo
    }


}