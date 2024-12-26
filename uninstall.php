<?php

/**
 * MRPacket
 * The MRPacket plugin enables you to import your order data from your WooCommerce shop directly to MRPacket.
 * 
 * @version 1.0.0
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

$table = esc_sql($wpdb->prefix . "mrpacket_tracking");
$wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table));

$table =  esc_sql($wpdb->prefix . "mrpacket_settings");
$wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table));

$table = esc_sql($wpdb->prefix . "mrpacket_logging");
$wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table));

delete_option('mrpacket_api_token');
delete_option('mrpacket_admin_email');
