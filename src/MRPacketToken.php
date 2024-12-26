<?php

/**
 * MRPacket
 * The MRPacket plugin enables you to import your order data from your WooCommerce shop directly to MRPacket.
 * 
 * @version 1.0.0
 * @link https://www.mrpacket.de/api
 * @license GPLv2
 * @author MRPacket <info@mrpacket.de>
 * 
 * Copyright (c) 2023 MRPacket
 */
?>
<?php

namespace MRPacketForWoo;

if (!defined('ABSPATH') || !defined('WPINC')) {
    exit;
}

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR .  'vendor'  . DIRECTORY_SEPARATOR . 'autoload.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'constants.php');

use MRPacket\Connect\Authentication;
use MRPacket\CrException;
use Exception;

class MRPacketToken
{
    private $authToken;
    public $helper;

    public function __construct($plugin, $mrpacketUserName, $mrpacketPassword)
    {
        $this->helper = new MRPacketHelper($plugin);

        $pluginInfo = $this->helper->getPluginInfo();
        $authClass = new Authentication($pluginInfo['shopFrameWorkName'], $pluginInfo['shopFrameWorkVersion'], $pluginInfo['shopModuleVersion']);

        try {
            $this->authToken = $authClass->getAuthToken($mrpacketUserName, $mrpacketPassword);
            $this->helper->messages['success'][] = __('The following auth token shall be used for any further calls to mrpacket: ', 'mrpacket') . $this->authToken;
        } catch (CrException $e) {

            $errorMsgException = 'Exception: Failed to retrieve auth token from mrpacket: ' . $e->getMessage();
            $errorMsgTrace  = 'Code: '  . $e->getCode() . "<br/>\n";
            $errorMsgTrace .= 'Trace: ' . $e->getTraceAsString();

            $this->helper->messages['error'][] = $errorMsgException;

            $this->helper->writeLog($errorMsgException, 'error');
            $this->helper->writeLog($errorMsgTrace, 'error');
        } catch (Exception $e) {
            $errorMsgException = 'Error while getting token from mrpacket: ' . $e->getMessage();
            $errorMsgTrace = 'Trace: ' . $e->getTraceAsString();

            $this->helper->messages['error'][] = $errorMsgException;
            $this->helper->writeLog($errorMsgException, 'error');
            $this->helper->writeLog($errorMsgTrace, 'error');
        }
    }

    public function getToken()
    {
        if (isset($this->authToken) && !empty($this->authToken)) {
            return $this->authToken;
        }

        return false;
    }
}
