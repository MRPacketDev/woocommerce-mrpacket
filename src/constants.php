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
if (!defined('ABSPATH') || !defined('WPINC')) {
    exit;
}
global $wpdb;
define("MRPACKET_TABLE_TRACKING", $wpdb->prefix . "mrpacket_tracking");
define("MRPACKET_TABLE_SETTINGS", $wpdb->prefix . "mrpacket_settings");
define("MRPACKET_TABLE_LOGGING",  $wpdb->prefix . "mrpacket_logging");
?>