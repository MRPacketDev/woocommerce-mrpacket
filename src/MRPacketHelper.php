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

use DateTime;
use DateTimeZone;


require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR .  'vendor'  . DIRECTORY_SEPARATOR . 'autoload.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'constants.php');

class MRPacketHelper
{
    public $plugin = null;
    public $helper;
    public $shopFrameWorkName;
    public $shopFrameWorkVersion;
    public $shopModuleVersion;
    public $pluginSettings;

    public $db;
    public $pluginInfo;
    public $pluginAdminUrl;
    public $messages;

    const MESSAGES_TYPE_ERROR = 'error';
    const MESSAGES_TYPE_SUCCESS = 'success';
    const MESSAGES_TYPE_WARNING = 'warning';
    const MESSAGES_TYPE_INFO = 'info';

    public function __construct($plugin)
    {
        global $wpdb;

        $this->db = $wpdb;

        $this->plugin           = $plugin;
        $this->pluginSettings   = $this->getPluginSettings();
        $this->pluginInfo       = $this->getPluginInfo();

        $this->messages = array(
            self::MESSAGES_TYPE_ERROR     => array(),
            self::MESSAGES_TYPE_SUCCESS     => array(),
        );
    }

    public function getPluginSettings()
    {

        $pluginSettings = array(
            'mrpacket_api_token'      => get_option('mrpacket_api_token'),
            'mrpacket_admin_email'    => get_option('mrpacket_admin_email'),
        );

        return $pluginSettings;
    }

    public function getMRPacketApiToken()
    {
        $this->pluginSettings = $this->getPluginSettings();
        if (!isset($this->pluginSettings['mrpacket_api_token']) || empty($this->pluginSettings['mrpacket_api_token'])) {
            $this->messages['error'][] = __('Error: Auth Token is missing! Please go to WooCommerce > Settings > MRPacket plugin settings, and enter your MRPacket username and passwort to get one!', 'mrpacket');
            $this->writeLog(__('Error: Auth Token is missing! Please go to WooCommerce > Settings > MRPacket plugin settings, and enter your MRPacket username and passwort to get one!', 'mrpacket'), self::MESSAGES_TYPE_ERROR);
        } else if (isset($this->pluginSettings['mrpacket_api_token']) && !empty($this->pluginSettings['mrpacket_api_token'])) {

            return $this->pluginSettings['mrpacket_api_token'];
        }

        return false;
    }

    public function getPluginInfo()
    {
        $this->shopFrameWorkName    = $this->plugin->get_shopframework();
        $this->shopFrameWorkVersion = get_option('woocommerce_version');
        $this->shopModuleVersion    = $this->plugin->get_version();

        $this->pluginAdminUrl       = admin_url('admin.php?page=mrpacket');

        $this->pluginInfo = array(
            'shopFrameWorkName'     => $this->shopFrameWorkName,
            'shopFrameWorkVersion'  => $this->shopFrameWorkVersion,
            'shopModuleVersion'     => $this->shopModuleVersion,
            'pluginAdminUrl'        => $this->pluginAdminUrl
        );

        return $this->pluginInfo;
    }

    public function convertToUtf8($var, $deep = TRUE)
    {
        if (is_array($var)) {
            foreach ($var as $key => $value) {
                if ($deep) {
                    $var[$key] = $this->convertToUtf8($value, $deep);
                } elseif (!is_array($value) && !is_object($value) && !mb_detect_encoding($value, 'utf-8', true)) {
                    $var[$key] = mb_convert_encoding($var, 'UTF-8');
                }
            }
            return $var;
        } elseif (is_object($var)) {
            foreach ($var as $key => $value) {
                if ($deep) {
                    $var->$key = $this->convertToUtf8($value, $deep);
                } elseif (!is_array($value) && !is_object($value) && !mb_detect_encoding($value, 'utf-8', true)) {
                    $var->$key = mb_convert_encoding(wp_json_encode($var), 'UTF-8');
                }
            }
            return $var;
        } else if ($var) {
            return (!mb_detect_encoding($var, 'utf-8', true)) ? mb_convert_encoding($var, 'UTF-8') : $var;
        } else {
            return $var;
        }
    }

    public function sendErrorMail()
    {
        if (!isset($this->pluginSettings['mrpacket_admin_email']) || empty($this->pluginSettings['mrpacket_admin_email'])) {
            $this->writeLog(__('Error sending mrpacket failure-mail: No valid email address set in mrpacket-plugin settings.', 'mrpacket'), self::MESSAGES_TYPE_ERROR);
            return;
        }

        $recipients = array(
            $this->pluginSettings['mrpacket_admin_email'],
        );

        $subject = __('WooCommerce: MRPacket-Plugin API-Error', 'mrpacket');

        $pluginTriggerUrl = esc_url($this->pluginInfo['pluginAdminUrl']) . '&refresh=1';

        $message = '
            <html>
                <head>
                    <title>' . __('WooCommerce: MRPacket-Plugin API-Error', 'mrpacket') . '</title>
                </head>
                <body>' . __('Dear Shop Owner,', 'mrpacket') . ' <br><br>
                    
                    <p>' . esc_html_e('a transfer of orders to the mrpacket backend by the "WooCommerce"-plugin failed.', 'mrpacket') . '</p>
        
                    <p><strong>' . __('To start the transfer again, please click here:', 'mrpacket') . '</strong></p>

                    <a href="' . $pluginTriggerUrl . '" target="_blank" title="' . __('Restart Transmission', 'mrpacket') . '">' . $pluginTriggerUrl . '</a>

                    <p>' . __('Please do not reply to this automatically generated email.', 'mrpacket') . '</p>

                    ' . __('Sincerely, ', 'mrpacket') . '<br>
                    ' . __('Your MRPacket-Plugin', 'mrpacket') . '
                </body>
            </html>
        ';

        $headers = array('Content-Type: text/html; charset=UTF-8');

        $res = wp_mail($recipients, $subject, $message, $headers);
    }

    public function ignoreOrderItem($item)
    {
        $productId = $item->get_product_id();
        $product = wc_get_product($productId);

        if (is_bool($product)) {
            $this->writeLog(__('Error at processing order item ', 'mrpacket') . $productId, self::MESSAGES_TYPE_ERROR, true);
            return true;
        }

        if ($product->is_virtual() == 'yes') {
            return true;
        }

        return false;
    }

    public function showNotices($messages = [])
    {
        if (empty($messages)) {
            $messages = $this->messages;
        }

        if (is_array($messages) && count($messages) > 0) {
            foreach ($messages as $type => $messages) {
                foreach ($messages as $value) {
                    switch ($type) {
                        case self::MESSAGES_TYPE_ERROR:
                            $class = 'notice notice-error is-dismissible';
                            break;
                        case self::MESSAGES_TYPE_SUCCESS:
                            $class = 'notice notice-success is-dismissible';
                            break;
                        case self::MESSAGES_TYPE_WARNING:
                            $class = 'notice notice-warning is-dismissible';
                            break;
                        case self::MESSAGES_TYPE_INFO:
                            $class = 'notice notice-info is-dismissible';
                            break;
                        default:
                            $class = 'notice notice-info is-dismissible';
                            break;
                    }

                    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($value));
                }
            }
        }
    }

    function writeLog($log, string $type = null, bool $addToMessages = false): void
    {
        if (!$log) {
            return;
        }

        if ($type == null) {
            $type = 'error';
        }

        $date = new DateTime();
        $dateNow = $date->setTimezone(new DateTimeZone($this->getDefaultTimezoneString()))->format('Y-m-d H:i:s');

        if (is_array($log) || is_object($log)) {
            $message = wp_json_encode($log);
        } else {
            $message = $log;
        }

        $this->db->query("DELETE FROM " . MRPACKET_TABLE_LOGGING . " WHERE dCreated < (NOW() - INTERVAL 15 DAY)");

        $this->db->insert(
            MRPACKET_TABLE_LOGGING,
            array(
                'type'      => $type,
                'message'   => $message,
                'dCreated'     => $dateNow,
                'dChanged'     => $dateNow
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );

        if ($addToMessages) {
            $this->plugin->helper->messages['error'][] = $message;
        }
    }

    public function getDefaultTimezoneString()
    {
        $defaultTimezone = get_option('timezone_string', 'Europe/Berlin');
        if (!isset($defaultTimezone) || empty($defaultTimezone)) {
            $defaultTimezone = 'Europe/Berlin';
        }

        return $defaultTimezone;
    }

    public function resetSettings()
    {
        $this->resetSettingsAdminMail();
        $this->resetSettingsApiToken();
    }

    public function resetSettingsAdminMail()
    {
        update_option('mrpacket_admin_email', '');
    }

    public function resetSettingsApiToken()
    {
        delete_option('mrpacket_api_token');
    }

    public function getPlugin()
    {
        return $this->plugin;
    }

    public function getLastCronRunDate()
    {
        $date = $this->db->get_row("SELECT `cValue` FROM " . MRPACKET_TABLE_SETTINGS . " WHERE `cName` = 'mrpacket_cron_last_run'", ARRAY_A);
        if ($date) {
            return $date['cValue'];
        }

        return null;
    }

    /**
     * @todo refactor to helper class ?
     *
     * @param array $packet
     * @param string $key
     * @return mixed
     */
    public function getOrderValue(array $packet, string $key)
    {
        if (!isset($packet['meta'])) {
            return null;
        }

        $meta = $packet['meta'];
        if (!isset($meta['order'])) {
            return null;
        }

        $orderData = $meta['order'];
        if (!isset($orderData[$key])) {
            return null;
        }

        return $orderData[$key];
    }
}
