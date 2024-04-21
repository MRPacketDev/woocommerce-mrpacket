<?php

/**
 * MRPacket
 * The MRPacket plugin enables you to import your order data from your WooCommerce shop directly to MRPacket.
 * 
 * @version 0.0.1
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

use MRPacket\Connect\Contract;
use MRPacket\CrException;

class MRPacketCancel
{
    public $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }


    public function removeParcelFromMRPacket($primaryKeyOfParcel = false)
    {
        $pk = (int) $primaryKeyOfParcel;
        if (!$pk) {
            return;
        }

        try {
            $contract = new Contract(
                $this->plugin->helper->shopFrameWorkName,
                $this->plugin->helper->shopFrameWorkVersion,
                $this->plugin->helper->shopModuleVersion,
                $this->plugin->helper->getMRPacketApiToken()
            );

            $contract->delete($pk);
            $this->plugin->helper->db->update(
                MRPACKET_TABLE_TRACKING,
                array(
                    'orderStatus'     => __('Removed', 'mrpacket'),
                    'archive'         => 0,
                ),
                array('pk' => $pk),
                array(
                    '%s',
                    '%d',
                    '%d',
                ),
                array(
                    '%s',
                    '%d',
                    '%d'
                )
            );

            $this->plugin->helper->messages['success'][] = "Successfully deleted packet with id $pk.";
        } catch (CrException $e) {
            $this->plugin->helper->writeLog('Error at deleting Packet ' . $pk . ' - ' . $e->getMessage());
            if ($e->getCode() == '404') {
                $this->plugin->helper->messages['error'][] = 'Packet not found (404): ' . $e->getMessage();
                $this->plugin->helper->db->update(
                    MRPACKET_TABLE_TRACKING,
                    array(
                        'orderStatus'     => __('Canceled', 'mrpacket'),
                        'archive'         => 0,
                    ),
                    array('pk' => $pk),
                    array(
                        '%s',
                        '%d',
                        '%d',
                    ),
                    array(
                        '%s',
                        '%d',
                        '%d'
                    )
                );
            }

            if (ENVIRONMENT == 'DEV') {
                $this->plugin->helper->messages['error'][] = 'Code: ' . $e->getCode();
                $this->plugin->helper->messages['error'][] = 'Trace: ' . $e->getTraceAsString();
            }
        } catch (Exception $e) {
            $this->plugin->helper->messages['error'][] = "Something else went terribly wrong: " . $e->getMessage() . "<br/>\n";
            $this->plugin->helper->messages['error'][] = 'Trace: ' . $e->getTraceAsString();
            $this->plugin->helper->writeLog('Error at deleting Packet ' . $pk . ' - ' . $e->getMessage());
        }
    }
}
