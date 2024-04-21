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

use DateTime;
use DateTimeZone;

class Frontend
{

	private $plugin;

	public $helper;

	public function __construct(Plugin $plugin)
	{

		$this->plugin = $plugin;

		$this->helper = new MRPacketHelper($plugin);
	}

	public function enqueue_styles()
	{
	}

	public function enqueue_scripts()
	{
	}

	public function mrpacketCron($plugin = false)
	{
		if ($plugin) {
			$this->plugin = $plugin;
		}

		$mrpacketCreate = new MRPacketCreate($this->plugin);
		$mrpacketCreate->sendOrdersToMRPacket();

		if (is_array($mrpacketCreate->plugin->helper->messages)) {
			$this->helper->writeLog($this->helper->messages, 'error');

			$isRefresh = isset($_POST['refresh']) ? (int) $_POST['refresh'] : 0;
			if ($isRefresh === 1) {
				do_action('show_mrpacket_notices', $mrpacketCreate->plugin->helper->messages);
			}
		}

		$date = new DateTime();
		$dateNow = $date->setTimezone(new DateTimeZone($this->helper->getDefaultTimezoneString()))->format('Y-m-d H:i:s');

		$db = $this->plugin->getDb();
		$db->update(
			MRPACKET_TABLE_SETTINGS,
			array(
				'cValue' 	=> $dateNow,
				'dChanged' 	=> $dateNow,
			),
			array('cName' => 'mrpacket_cron_last_run'),
			array(
				'%s',
				'%s',
			),
			array('%s')
		);
	}
}
