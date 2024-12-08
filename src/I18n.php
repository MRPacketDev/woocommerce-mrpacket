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

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR .  'vendor'  . DIRECTORY_SEPARATOR . 'autoload.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'constants.php');


class I18n
{

	private $domain;

	public function __construct()
	{

		__('MRPacket', 'mrpacket');
		__('The MRPacket plugin enables you to transfer order data from your WooCommerce shop directly to MRPacket.', 'mrpacket');
	}

	public function load_plugin_textdomain()
	{
		\load_plugin_textdomain(
			$this->domain,
			false,
			dirname(dirname(\plugin_basename(__FILE__))) . '/languages/'
		);
	}

	public function set_domain($domain)
	{
		$this->domain = $domain;
	}
}
