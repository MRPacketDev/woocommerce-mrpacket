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

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR .  'vendor'  . DIRECTORY_SEPARATOR . 'autoload.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'constants.php');

class Ajax
{

    protected $plugin;

    public $helper;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->helper = new MRPacketHelper($plugin);
        $this->init();
    }

    public function init()
    {
        $this->add_ajax_events();
    }

    public function get_endpoint($request = '')
    {
        return esc_url_raw(add_query_arg('wc-ajax', $request, remove_query_arg(array('remove_item', 'add-to-cart', 'added-to-cart'))));
    }

    public function define_ajax()
    {
        if (!empty($_GET['wc-ajax'])) {
            if (!defined('DOING_AJAX')) {
                define('DOING_AJAX', true);
            }
            if (!defined('WC_DOING_AJAX')) {
                define('WC_DOING_AJAX', true);
            }
            if (!WP_DEBUG || (WP_DEBUG && !WP_DEBUG_DISPLAY)) {
                @ini_set('display_errors', 0);
            }
            $GLOBALS['wpdb']->hide_errors();
        }
    }

    private function wc_ajax_headers()
    {
        \send_origin_headers();
        @header('Content-Type: text/html; charset=' . get_option('blog_charset'));
        @header('X-Robots-Tag: noindex');
        \send_nosniff_header();
        \nocache_headers();
        \status_header(200);
    }

    public function do_wc_ajax()
    {
        global $wp_query;
        if (!empty($_GET['wc-ajax'])) {
            $wp_query->set('wc-ajax', sanitize_text_field($_GET['wc-ajax']));
        }
        if ($action = $wp_query->get('wc-ajax')) {
            self::wc_ajax_headers();
            \do_action('wc_ajax_' . sanitize_text_field($action));
            die();
        }
    }

    public function add_ajax_events()
    {
        \add_action('wp_ajax_cancel_partial', array($this, 'cancel_partial'));
        \add_action('wp_ajax_nopriv_cancel_partial', array($this, 'cancel_partial'));

        \add_action('wp_ajax_reenable_partial', array($this, 'reenable_partial'));
        \add_action('wp_ajax_nopriv_reenable_partial', array($this, 'reenable_partial'));

        \add_action('wp_ajax_archive_partial', array($this, 'archive_partial'));
        \add_action('wp_ajax_nopriv_archive_partial', array($this, 'archive_partial'));
    }

    public function cancel_partial()
    {
        $parcialData = $_REQUEST['parcialData'];
        $result = false;
        if (is_array($parcialData)) {
            $mrpacketCancel = new MRPacketCancel($this->plugin);
            foreach ($parcialData as $row => $columns) {
                $primaryKeyOfParcel = $columns[2];
                $result = $mrpacketCancel->removeParcelFromMRPacket($primaryKeyOfParcel);
            }
        }

        wp_send_json_success($result);
        die();
    }

    public function reenable_partial()
    {
        $parcialData = $_REQUEST['parcialData'];
        if (is_array($parcialData)) {
            $ordersToReSubmit = [];
            foreach ($parcialData as $row => $columns) {
                if ($columns[7] == 'Canceled') {
                    $ordersToReSubmit[$columns[1]] = $columns[3];
                } else {
                    unset($parcialData[$row]);
                }
            }

            if (is_array($ordersToReSubmit) && (count($ordersToReSubmit) > 0)) {
                $mrpacketCreate = new MRPacketCreate($this->plugin);
                $mrpacketCreate->addOrdersToResubmit($ordersToReSubmit);
                $mrpacketCreate->sendOrdersToMRPacket();
            }
        }
        wp_send_json_success();
        die();
    }

    public function archive_partial()
    {
        $parcialData = $_REQUEST['parcialData'];
        if (is_array($parcialData)) {
            $ordersToArchive = [];
            foreach ($parcialData as $row => $columns) {
                if ($columns[7] == 'Canceled') {
                    $ordersToArchive[$columns[1]] = $columns[3];
                } else {
                    unset($parcialData[$row]);
                }
            }

            if (is_array($ordersToArchive) && (count($ordersToArchive) > 0)) {
                foreach ($ordersToArchive as $id => $orderId) {

                    $this->plugin->helper->db->update(
                        MRPACKET_TABLE_TRACKING,
                        array(
                            'archive' => 1,
                        ),
                        array('id' => $id),
                        array(
                            '%d',
                            '%d',
                        ),
                        array(
                            '%d',
                            '%d'
                        )
                    );

                    $this->plugin->helper->messages['warning'][] = 'Order with ID: "' . $orderId . '" archived!';
                }
            }
        }
        wp_send_json_success();
        die();
    }
}
