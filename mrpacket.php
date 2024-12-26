<?php

namespace MRPacketForWoo;

/**
 * MRPacket
 *
 * @package					MRPacket
 * @author					MRPacket <info@mrpacket.de>
 * @copyright				2024 MRPacket
 * @license					GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:				MRPacket
 * Plugin URI:				https://www.mrpacket.de
 * Description:				The MRPacket plugin enables you to import your order data from your WooCommerce shop directly to MRPacket.
 * Version:					1.0.0
 * Requires at least:		6.7
 * Requires PHP:			7.3
 * 
 * WC requires at least:	3.0.0
 * WC tested up to:			6.6.1
 *
 * Author:					MRPacket
 * Author URI:				https://www.mrpacket.de/
 * License:					GPL v2 or later
 * License URI:				https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
'MRPacket' is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
'MRPacket' is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with 'MRPacket'. If not, see https://www.gnu.org/licenses/gpl-2.0.html and LICENSE.txt.
*/

/**
 * Prevent Data leaks
 **/
if (!defined('ABSPATH') || !defined('WPINC')) {
	exit; // Exit if accessed directly
}

/**
 * Define the Plugins current version
 * NOTE: Update this value only if plugin changes!
 **/
if (!defined('MRPACKET_PLUGIN_VERSION')) {
	define('MRPACKET_PLUGIN_VERSION', '1.0.0');
}

/**
 * Autoloading
 **/
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
	require_once dirname(__FILE__) . '/vendor/autoload.php';
}

/**
 * Add Custom Cronjob Schedules
 * (needs to be done here to use in activation hook ...)
 */
\add_filter('cron_schedules', function ($schedules) {
	if (!isset($schedules['30sec'])) {
		$schedules['30sec'] = array(
			'interval' => 30,
			'display' => __('Once every 30 seconds', 'mrpacket')
		);
	}
	if (!isset($schedules['5min'])) {
		$schedules['5min'] = array(
			'interval' => 5 * 60,
			'display' => __('Once every 5 minutes', 'mrpacket')
		);
	}
	if (!isset($schedules['30min'])) {
		$schedules['30min'] = array(
			'interval' => 30 * 60,
			'display' => __('Once every 30 minutes', 'mrpacket')
		);
	}
	return $schedules;
});

/**
 * The code that runs during plugin activation.
 * This action is documented in src/Activator.php
 */
\register_activation_hook(__FILE__, '\MRPacketForWoo\Activator::getInstance');

/**
 * The code that runs during plugin deactivation.
 * This action is documented in src/Deactivator.php
 */
\register_deactivation_hook(__FILE__, '\MRPacketForWoo\Deactivator::deactivate');

/**
 * Begins execution of the plugin.
 */
\add_action('plugins_loaded', function () {

	/**
	 * Check if WooCommerce is active,
	 * if not, stop plugin execution
	 **/
	if (!class_exists('WooCommerce')) {
		\add_action('admin_notices', function () {
			$class = 'notice notice-error is-dismissible';
			$message = __('Error: Please activate the main shop-plugin "WooCommerce" first to use the plugin "MRPacket". Plugin "MRPacket" has been deactivated because this dependency is missing.', 'mrpacket');

			printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));

			deactivate_plugins(__FILE__);
		});
		return;
	}

	// Get information about plugin
	$plugin_file_data = get_file_data(__FILE__, array('Plugin Name', 'Version'));

	$plugin_data = array();
	$plugin_data['plugin_name'] 	= array_shift($plugin_file_data);
	$plugin_data['textdomain']		= 'mrpacket';
	$plugin_data['slug']			= 'mrpacket';
	$plugin_data['shopframework'] 	= 'WordPress/WooCommerce';

	$plugin_data['plugin_dir'] 		= trailingslashit(plugin_dir_path(__FILE__));
	$plugin_data['plugin_url'] 		= trailingslashit(plugin_dir_url(__FILE__));
	$plugin_data['version'] 		= MRPACKET_PLUGIN_VERSION;

	// Make sure plugin updates are performed if version has been changed
	if (version_compare(MRPACKET_PLUGIN_VERSION, get_option('mrpacket_plugin_version'), '!=')) {
		\MRPacketForWoo\Activator::getInstance();
	}

	// Start the plugin
	$plugin = new Plugin($plugin_data);
	$plugin->run();
});

add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

if (! function_exists('request_filesystem_credentials')) {
	require_once(ABSPATH . 'wp-admin/includes/file.php');
}

// Initialize WP Filesystem
global $wp_filesystem;
if (! is_object($wp_filesystem)) {
	WP_Filesystem();
}
