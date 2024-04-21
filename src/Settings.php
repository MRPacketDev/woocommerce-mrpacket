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

class Settings
{
    protected $plugin;
    protected $tab_name;
    protected $authToken;
    protected $setting_name;
    protected $carrier_products = array();
    public $helper;
    public $orderStatusFromSystem = array();

    public function __construct($plugin)
    {
        if (!current_user_can('edit_mrpacket')) {
            __("You dont have permission to manage options. Please contact site administrator. You need the capability (access right) \'edit_mrpacket\'.", 'mrpacket');
            return;
        }

        $this->plugin = $plugin;
        $this->helper = new MRPacketHelper($plugin);
        $this->setting_name = \str_replace('-', '_', $this->plugin->get_slug());
        $this->tab_name = $this->plugin->get_name();

        \add_filter('woocommerce_settings_tabs_array', array($this, 'addSettingsTab'), 90);
        \add_action('woocommerce_settings_tabs_' . $this->setting_name . '_tab', array($this, 'settingsTab'));
        \add_action('woocommerce_update_options_' . $this->setting_name . '_tab', array($this, 'updateSettings'));
    }

    public function addSettingsTab($settings_tabs)
    {
        $tab_name = $this->setting_name . '_tab';
        $settings_tabs[$tab_name] = __('MRPacket Settings', 'mrpacket');

        return $settings_tabs;
    }

    public function settingsTab()
    {
        $resetSettings = isset($_POST['mrpacket_reset_settings']) ? (int) $_POST['mrpacket_reset_settings'] : 0;
        if ($resetSettings === 1) {

            $this->helper->resetCrSettings();
        }

        $token = $this->helper->getMRPacketApiToken();
        $orderStatusFromSystem = $this->orderStatusFromSystem = wc_get_order_statuses();
        $orderStatusSelected = get_option('orderstatus', array('wc-processing'));
        $mrpacket_cron_last_run = $this->helper->getLastCronRunDate();

        do_action('show_mrpacket_notices', $this->helper->messages);
        $adminURL = $this->helper->pluginAdminUrl;

        require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'page.backend.settings.php');
    }

    public function updateSettings()
    {
        if (isset($_REQUEST['mrpacket_settings_form_nonce']) && !wp_verify_nonce($_REQUEST['mrpacket_settings_form_nonce'], 'mrpacket_update')) {
            $this->helper->messages['error'][] = __('Sorry, your nonce was not correct. Please try again.', 'mrpacket');
        } else {
            $userName = '';
            $password = '';
            if (isset($_POST['mrpacket_api_username'])) {
                $userName = (string) trim(sanitize_user($_POST['mrpacket_api_username']));
            }
            if (isset($_POST['mrpacket_api_password'])) {
                $password = (string) trim($_POST['mrpacket_api_password']);
            }

            if (!empty($userName) && !empty($password)) {
                $mrpacketToken = new MRPacketToken($this->plugin, $userName, $password);
                if (is_array($mrpacketToken->helper->messages['success'])) {
                    foreach ($mrpacketToken->helper->messages['success'] as $key => $value) {
                        $this->helper->messages['success'][] = $value;
                        $this->helper->writeLog($this->helper->messages['success'], 'error');
                    }
                }

                if (is_array($mrpacketToken->helper->messages['error'])) {
                    foreach ($mrpacketToken->helper->messages['error'] as $key => $value) {
                        $this->helper->messages['error'][] = $value;
                        $this->helper->writeLog($this->helper->messages['error'], 'error');
                    }
                }

                if (!$mrpacketToken->getToken() || empty($mrpacketToken->getToken())) {
                    $this->helper->messages['error'][] = __('Your username or password were invalid.', 'mrpacket');
                    $this->helper->writeLog($this->helper->messages['error'], 'error');
                } else {

                    update_option('mrpacket_api_token', $mrpacketToken->getToken());
                }
            }

            if ((isset($_POST['mrpacket_transfer_settings']) && !empty($_POST['mrpacket_transfer_settings'])) && $_POST['mrpacket_transfer_settings'] != '999') {
                $mrpacket_transfer_settings = $_POST['mrpacket_transfer_settings'];
                update_option('orderstatus', $mrpacket_transfer_settings);

                $this->helper->messages['success'][] = __('Status settings updated successfully.', 'mrpacket');
            } else {
                update_option('orderstatus', array('999'));
            }

            $mrpacket_admin_email = isset($_POST['mrpacket_admin_email']) ? sanitize_email($_POST['mrpacket_admin_email']) : '';
            if (!empty($mrpacket_admin_email)) {
                if (!filter_var($mrpacket_admin_email, FILTER_VALIDATE_EMAIL)) {
                    $this->helper->messages['error'][] = __('Please enter a valid email address!', 'mrpacket');
                    return;
                }

                if ($mrpacket_admin_email !== get_option('mrpacket_admin_email')) {
                    update_option('mrpacket_admin_email', $mrpacket_admin_email);
                    $this->helper->messages['success'][] = __('Successfully updated E-Mail address!', 'mrpacket');
                }
            }

            if (empty($mrpacket_admin_email)) {
                $this->helper->resetCrSettingsAdminMail();
            }
        }
    }
}
