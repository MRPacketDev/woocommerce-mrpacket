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
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

class Activator
{

	public $db;
	public $version;
	public $currentPluginVersionInstalled;

	public function __construct()
	{
		global $wpdb;
		$this->db = $wpdb;

		$this->handlePluginUpdateByVersion();
	}


	public static function getInstance()
	{
		return new self();
	}


	public function handlePluginUpdateByVersion()
	{
		$this->createDatabaseTables();
		$this->activateCron();

		$this->currentPluginVersionInstalled = get_option('mrpacket_plugin_version');

		update_option('mrpacket_plugin_version', MRPACKET_PLUGIN_VERSION);
	}

	public function createDatabaseTables()
	{

		$charset_collate = $this->db->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS " . MRPACKET_TABLE_TRACKING . " (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			pk mediumint(9) NOT NULL,
			orderId mediumint(9) NOT NULL,
			orderReference tinytext NOT NULL,
			orderStatus tinytext NOT NULL,
			archive mediumint(9) NOT NULL,
			dCreated TIMESTAMP NULL DEFAULT NULL,
			dChanged TIMESTAMP NOT NULL DEFAULT NOW(),
			UNIQUE KEY id (id), 
			PRIMARY KEY  (id)
		) " . $charset_collate . ";";

		dbDelta($sql);

		$sql = "CREATE TABLE IF NOT EXISTS " . MRPACKET_TABLE_SETTINGS . " (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			cName tinytext NOT NULL,
			cValue tinytext NOT NULL,
			dCreated TIMESTAMP NULL DEFAULT NULL,
			dChanged TIMESTAMP NOT NULL DEFAULT NOW(),
			UNIQUE KEY id (id), 
			PRIMARY KEY  (id)
		) " . $charset_collate . ";";

		dbDelta($sql);

		$sql = "INSERT INTO " . MRPACKET_TABLE_SETTINGS . " (id, cName, cValue, dCreated, dChanged) 
				VALUES (1, 'mrpacket_cron_last_run', NOW(), NOW(), NOW())
				ON DUPLICATE KEY UPDATE 
					id = id,
					cName = 'mrpacket_cron_last_run',
					cValue = NOW(),
					dCreated = dCreated,
					dChanged = NOW();
		";

		$this->db->query($sql);

		$sql = "INSERT INTO `" . MRPACKET_TABLE_SETTINGS . "` (id, cName, cValue, dCreated, dChanged) 
					VALUES (2, 'mrpacket_plugin_installation_date', NOW(), NOW(), NOW())
					ON DUPLICATE KEY UPDATE 
						id = id,
						cName = 'mrpacket_plugin_installation_date',
						cValue = NOW(),
						dCreated = dCreated,
						dChanged = NOW(); 
				";

		$this->db->query($sql);

		$sql = "CREATE TABLE IF NOT EXISTS " . MRPACKET_TABLE_LOGGING . " (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			type tinytext NOT NULL,
			message text NOT NULL,
			dCreated TIMESTAMP NULL DEFAULT NULL,
			dChanged TIMESTAMP NOT NULL DEFAULT NOW(),
			UNIQUE KEY id (id), 
			PRIMARY KEY  (id)
		) " . $charset_collate . ";";

		dbDelta($sql);
	}


	public function activateCron()
	{
		if (!wp_next_scheduled('mrpacket_cron_event')) {
			wp_schedule_event(time(), '5min', 'mrpacket_cron_event');
		}
	}
}
