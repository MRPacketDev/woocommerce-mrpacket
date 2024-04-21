<?php

/**
 * MRPacket
 * The MRPacket plugin enables you to import your order data from your WooCommerce shop directly to MRPacket.
 * 
 * @version 0.0.1
 * @link https://www.mrpacket.de
 * @license GPLv2
 * @author MRPacket <info@mrpacket.de>
 * 
 * Copyright (c) 2024 MRPacket
 */
?>
<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "mrpacket_tracking");
$wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "mrpacket_settings");
$wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "mrpacket_logging");

delete_option('mrpacket_api_token');
delete_option('mrpacket_admin_email');
