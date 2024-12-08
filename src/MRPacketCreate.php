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

use MRPacket\Connect\Contract;
use MRPacket\CrException;
use Olifolkerd\Convertor\Convertor;
use DateTime;
use DateTimeZone;
use Exception;
use MRPacket\Connect\ContractPacket;
use MRPacketForWoo\Enums\OrderStatus;
use Automattic\WooCommerce\Utilities\OrderUtil;

class MRPacketCreate
{

	public $shopFrameWorkName;
	public $shopFrameWorkVersion;
	public $shopModuleVersion;
	public $plugin;

	public $status;
	public $ordersToReSubmit = [];

	public function __construct($plugin)
	{
		$this->plugin = $plugin;
	}

	public function sendOrdersToMRPacket()
	{
		if ($this->plugin->helper->getMRPacketApiToken()) {
			$orders = $this->getOrdersToSendToMRPacket();

			if (!$orders) {
				$this->plugin->helper->messages['info'][] = __('No new orders could be found for transfer to MRPacket business with allowed status (See Settings). List is up to date.', 'mrpacket');
				$this->plugin->helper->writeLog($this->plugin->helper->messages, 'error');
			} else if (is_array($orders) && count($orders) > 0) {
				foreach ($orders as $order) {
					try {
						$encodedObject = $this->plugin->helper->convertToUtf8($order);
						$this->sendOrderToMRPacketBusinessBackend($this->prepareOrderForMRPacketApi($encodedObject));
					} catch (Exception $e) {
						$this->plugin->helper->messages['error'][] = __('Error Message: Error while preparing package/oder data for API-call', 'mrpacket')  . $e->getMessage();
						$this->plugin->helper->writeLog('Fehlermeldung: Fehler beim Vorbereiten der Paketdaten für den API-Aufruf: '  . $e->getMessage(), 'error');
					}
				}
			}
		}
	}

	public function addOrdersToResubmit($ordersToReSubmit)
	{
		$this->ordersToReSubmit = [];

		$this->ordersToReSubmit = $ordersToReSubmit;
	}

	public function getAllNewOrdersToSubmit()
	{
		global $wpdb;

		$pluginInstallationDate = $this->plugin->helper->db->get_var("
			SELECT cValue FROM  " . MRPACKET_TABLE_SETTINGS . " 
			WHERE cName='mrpacket_plugin_installation_date'
		");

		$date = new DateTime($pluginInstallationDate,  new DateTimeZone($this->plugin->helper->getDefaultTimezoneString()));
		$pluginInstallationDateTimestamp = esc_sql($date->format('Y-m-d H:i:s'));

		$orderStatusSettings = get_option('orderstatus', array('wc-processing'));
		if (!is_array($orderStatusSettings) || $orderStatusSettings[0] == '999') {
			return;
		}

		$trackingTableName = esc_sql(MRPACKET_TABLE_TRACKING);
		if (OrderUtil::custom_orders_table_usage_is_enabled()) {
			$postsTableName = esc_sql($wpdb->prefix . "wc_orders");
			$query = "
			SELECT orders.ID
			FROM %i AS orders
			LEFT JOIN %i AS tracking ON tracking.orderId = orders.ID
			WHERE orders.status IN (" . implode(',', array_fill(0, count($orderStatusSettings), '%s')) . ")
				AND UNIX_TIMESTAMP(orders.date_created_gmt) >= UNIX_TIMESTAMP(%s)
				AND UNIX_TIMESTAMP(orders.date_created_gmt) < CURRENT_TIMESTAMP
				AND orders.type = 'shop_order'
				AND tracking.orderId IS NULL
			GROUP BY orders.ID
		";
		} else {
			$postsTableName = esc_sql($wpdb->prefix . "posts");
			$query = "
			SELECT posts.ID
			FROM %i AS posts
			LEFT JOIN %i AS tracking ON tracking.orderId = posts.ID
			WHERE posts.post_status IN (" . implode(',', array_fill(0, count($orderStatusSettings), '%s')) . ")
            AND UNIX_TIMESTAMP(posts.post_date) >= UNIX_TIMESTAMP(%s)
            AND UNIX_TIMESTAMP(posts.post_date) < CURRENT_TIMESTAMP
            AND posts.post_type = 'shop_order'
            AND tracking.orderId IS NULL
			GROUP BY posts.ID
			";
		}

		$params = array_merge(
			$orderStatusSettings,
			[$pluginInstallationDateTimestamp]
		);
		return $wpdb->get_results($wpdb->prepare($query, $postsTableName, $trackingTableName, ...$params));
	}

	public function getOrdersToSendToMRPacket()
	{
		if (is_array($this->ordersToReSubmit) && count($this->ordersToReSubmit) > 0) {
			$ordersToSubmit = $this->ordersToReSubmit;
		} else {
			$ordersToSubmit = $this->getAllNewOrdersToSubmit();
		}

		if (is_array($ordersToSubmit) && count($ordersToSubmit) > 0) {
			$result = array();
			foreach ($ordersToSubmit as $order) {
				$orderId = $order->ID;
				try {
					$result[] = wc_get_order($order);
				} catch (\UnexpectedValueException $ex) {
					if ($ex->getCode() == 0) {
						$this->plugin->helper->db->delete(MRPACKET_TABLE_TRACKING, array('orderId' => $orderId));
						$this->plugin->helper->messages['warning'][] = 'Order with ID: "' . $orderId . '" skipped! Reason: The order no longer exists in the shop-system!';
					}
				}
			}

			return $result;
		}

		return false;
	}

	public function setDefaults(): array
	{
		$orderData = [];

		$orderData['receiver']['firstname'] 	= '';
		$orderData['receiver']['lastname'] 		= '';
		$orderData['receiver']['company'] 		= '';
		$orderData['receiver']['street'] 		= '';
		$orderData['receiver']['street_number']	= '';
		$orderData['receiver']['zip']			= '';
		$orderData['receiver']['city'] 			= '';
		$orderData['receiver']['phone_nr'] 		= '';
		$orderData['receiver']['email'] 		= '';
		$orderData['receiver']['country'] 		= '';
		$orderData['receiver']['email'] 		= '';

		// Shipper
		$orderData['shipper']['street']			= get_option('woocommerce_store_address');
		$orderData['shipper']['street_number']  = get_option('woocommerce_store_address_2');
		$orderData['shipper']['zip']			= get_option('woocommerce_store_postcode');
		$orderData['shipper']['city']			= get_option('woocommerce_store_city');
		$orderData['shipper']['country']		= '';

		$storeCountryAndState = get_option('woocommerce_default_country');
		if ($storeCountryAndState) {
			$splittedCountry = explode(":", $storeCountryAndState);
			$storeCountry = $splittedCountry[0];
			$orderData['shipper']['country']	= $storeCountry;
		}

		$orderData['packet']['length']			= null;
		$orderData['packet']['width']			= null;
		$orderData['packet']['height']			= null;
		$orderData['packet']['weight']			= 0;

		return $orderData;
	}

	public function prepareOrderForMRPacketApi($order): array
	{
		$orderData = $this->setDefaults();

		$unitsConvertor = new Convertor();
		$orderData['receiver']['firstname']	= $order->get_shipping_first_name();
		$orderData['receiver']['lastname']		= $order->get_shipping_last_name();
		$orderData['receiver']['company']		= $order->get_shipping_company();

		$orderData['receiver']['phone_nr']			= $order->get_billing_phone();
		$orderData['receiver']['email']			= $order->get_billing_email();

		$address = 	trim($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2());
		$match = preg_match('/^([^\d]*[^\d\s]) *(\d.*)$/', $address, $result);

		if ($match !== 0) {
			$street = $result[1];
			$streetNumber = $result[2];
		} else {
			$street = $order->get_shipping_address_1();
			$streetNumber = $order->get_shipping_address_2();
		}

		$orderData['receiver']['street']		= $street;
		$orderData['receiver']['street_number']	= $streetNumber;
		$orderData['receiver']['zip']	= $order->get_shipping_postcode();
		$orderData['receiver']['city']			= $order->get_shipping_city();
		$orderData['receiver']['country']		= $order->get_shipping_country();

		$orderData['order']['id']				= $order->get_id();
		$orderData['order']['reference']		= $order->get_order_key();

		$orderPositions = $order->get_items() ? $order->get_items() : 0;

		$productCount = 0;
		foreach ($orderPositions as $item_values) {
			if ($this->plugin->helper->ignoreOrderItem($item_values)) {
				continue;
			}

			for ($i = 0; $i < $item_values->get_quantity(); $i++) {

				$orderData['products'][$productCount]['name']	= $item_values->get_name() ?  $item_values->get_name() : '';

				$from_unit_dimension = strtolower(get_option('woocommerce_dimension_unit', 'cm'));
				if (($item_values->get_product()->get_length() != null) && ($item_values->get_product()->get_length() > 0)) {
					$unitsConvertor->from($item_values->get_product()->get_length(), $from_unit_dimension);
					$lengthInCm = $unitsConvertor->to('cm');
					$orderData['products'][$productCount]['length']	= $lengthInCm  ?  $lengthInCm : null;
				}

				if (($item_values->get_product()->get_width() != null) && ($item_values->get_product()->get_width() > 0)) {
					$unitsConvertor->from($item_values->get_product()->get_width(), $from_unit_dimension);
					$widthInCm = $unitsConvertor->to('cm');
					$orderData['products'][$productCount]['width']	= $widthInCm   ?  $widthInCm  : null;
				}

				if (($item_values->get_product()->get_height() != null) && ($item_values->get_product()->get_height() > 0)) {
					$unitsConvertor->from($item_values->get_product()->get_height(), $from_unit_dimension);
					$heighthInCm = $unitsConvertor->to('cm');
					$orderData['products'][$productCount]['height']	= $heighthInCm  ?  $heighthInCm : null;
				}

				$weightInKg = false;
				if (($item_values->get_product()->get_weight() != null) && ($item_values->get_product()->get_weight() > 0)) {
					$from_unit_weight = strtolower(get_option('woocommerce_weight_unit', 'kg'));
					if ($from_unit_weight == 'lbs') {
						$from_unit_weight = 'lb';
					}
					$unitsConvertor->from($item_values->get_product()->get_weight(), $from_unit_weight);
					$weightInKg = $unitsConvertor->to('kg');
				}
				$orderData['products'][$productCount]['weight']	= $weightInKg ? $weightInKg : 0;

				$productCount++;
			}
		}

		if (!is_array($orderData['products'])) {
			return $orderData;
		}

		$orderData['packet']['length']	= 0;
		$orderData['packet']['width']	= 0;
		$orderData['packet']['height'] = 0;

		if ($productCount == 1) {
			$orderData['packet']['length']	= $orderData['products'][0]['length'];
			$orderData['packet']['width']	= $orderData['products'][0]['width'];
			$orderData['packet']['height'] = $orderData['products'][0]['height'];
		}

		foreach ($orderData['products'] as $singleProduct) {
			$orderData['packet']['weight'] += $singleProduct['weight'];
		}

		return $orderData;
	}

	public function sendOrderToMRPacketBusinessBackend($orderData)
	{
		try {
			$mContract = new Contract(
				$this->plugin->helper->getPluginInfo()['shopFrameWorkName'],
				$this->plugin->helper->getPluginInfo()['shopFrameWorkVersion'],
				$this->plugin->helper->getPluginInfo()['shopModuleVersion'],
				$this->plugin->helper->getMRPacketApiToken()
			);

			$this->status = $mContract->create($this->getPacketData($orderData));
			if ($this->status['success']) {
				$newPacket = $this->status['data'];
				$packetOrderId = (int) $this->plugin->helper->getOrderValue($newPacket, 'id');
				if (is_array($this->ordersToReSubmit) && count($this->ordersToReSubmit) > 0) {
					foreach ($this->ordersToReSubmit as $id => $orderId) {
						if ($packetOrderId == $orderId) {
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
						}
					}
				}

				$this->plugin->helper->db->insert(MRPACKET_TABLE_TRACKING, array(
					'pk' 				=> $newPacket['id'],
					'orderId' 			=> $packetOrderId,
					'orderReference'	=> $this->plugin->helper->getOrderValue($newPacket, 'reference'),
					'orderStatus'		=> OrderStatus::getKeys()[OrderStatus::SYNCED],
					'dCreated'			=> gmdate('Y-m-d H:i:s'),
					'dChanged'			=> gmdate('Y-m-d H:i:s'),
					'archive'           => 0,
				));

				$this->plugin->helper->messages['success'][] = __('Successfully created parcel. Order ID: ', 'mrpacket') . $packetOrderId;

				$logRequest = "(pretty printing parcel data)";
				$logRequest .= "<pre>";
				$logRequest .= wp_json_encode($this->status['data']);
				$logRequest .= "</pre>";

				$this->plugin->helper->writeLog($logRequest, 'success');
				return;
			}

			if (is_array($this->status['errors'])) {
				foreach ($this->status['errors'] as $msg) {
					$this->plugin->helper->messages['error'][] = 'Error: ' . $msg;
				}
			}

			$this->plugin->helper->sendErrorMail();
			$this->plugin->helper->messages['error'][] = __('Connection/Parcel create error. Please check the shop-admins E-Mail inbox for details.', 'mrpacket');
			$this->plugin->helper->writeLog($this->plugin->helper->messages['error'], 'error');
		} catch (CrException $e) {
			if ($e->getCode() == 401) {
				$this->plugin->helper->resetSettingsApiToken();
				$this->plugin->helper->messages['error'][] = __('Your Token seems to be invalid. Please go to the WooCommerce > Settings > MRPacket plugin settings dialogue and request a new one by entering your password and username again.', 'mrpacket');
				$this->plugin->helper->writeLog($this->plugin->helper->messages['error'], 'error');
			} else {
				$errorMsgException = 'Exception: error in communication with mrpacket server: ' . $e->getMessage();
				$errorMsgTrace  = 'Code: '  . $e->getCode() . "<br/>\n";
				$errorMsgTrace .= 'Trace: ' . $e->getTraceAsString();

				$this->plugin->helper->messages['error'][] = $errorMsgException;
				$this->plugin->helper->writeLog($errorMsgException, 'error');
				$this->plugin->helper->writeLog($errorMsgTrace, 'error');
			}
		} catch (Exception $e) {
			$errorMsgException = "Something else went terribly wrong: " . $e->getMessage();
			$errorMsgTrace = 'Trace: ' . $e->getTraceAsString();

			$this->plugin->helper->messages['error'][] = $errorMsgException;
			$this->plugin->helper->writeLog($errorMsgException, 'error');
			$this->plugin->helper->writeLog($errorMsgTrace, 'error');
		}
	}

	protected function getPacketData(array $orderData): ContractPacket
	{
		$data = new ContractPacket();

		// Receiver
		$data->receiver['firstname'] 	= $orderData['receiver']['firstname'];
		$data->receiver['lastname']		= $orderData['receiver']['lastname'];
		$data->receiver['company']		= $orderData['receiver']['company'];
		$data->receiver['phone_nr']			= $orderData['receiver']['phone_nr'];
		$data->receiver['email']			= $orderData['receiver']['email'];
		$data->receiver['street']			= $orderData['receiver']['street'];
		$data->receiver['street_number']	= $orderData['receiver']['street_number'];
		$data->receiver['zip']		= $orderData['receiver']['zip'];
		$data->receiver['city']			= $orderData['receiver']['city'];
		$data->receiver['country']		= $orderData['receiver']['country'];

		// Shipper
		$data->shipper['street'] = $orderData['shipper']['street'];
		$data->shipper['street_number'] = $orderData['shipper']['street_number'];
		$data->shipper['zip'] = $orderData['shipper']['zip'];
		$data->shipper['city'] = $orderData['shipper']['city'];
		$data->shipper['country'] = $orderData['shipper']['country'];

		// Packet
		$data->packet['length'] 	= $orderData['packet']['length'];
		$data->packet['width'] 	= $orderData['packet']['width'];
		$data->packet['height'] 	= $orderData['packet']['height'];
		$data->packet['weight'] 	= $orderData['packet']['weight'];
		$data->packet['meta'] 	= [
			'order' => [
				'id' => $orderData['order']['id'],
				'reference' => $orderData['order']['reference'],
			]
		];

		return $data;
	}
}
