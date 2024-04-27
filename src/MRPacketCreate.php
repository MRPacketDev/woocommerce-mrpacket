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
use MRPacket\Connect\ContractPacket;
use MRPacket\CrException;
use Olifolkerd\Convertor\Convertor;
use DateTime;
use DateTimeZone;
use Exception;

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
			if (!$orders || !is_array($orders)) {
				$this->plugin->helper->messages['info'][] = __('No new orders could be found for transfer to MRPacket business with allowed status (See Settings). List is up to date.', 'mrpacket');
				$this->plugin->helper->writeLog($this->plugin->helper->messages, 'error');
				return;
			}

			if (count($orders) > 0) {
				foreach ($orders as $order) {
					try {
						$encodedObject = $this->plugin->helper->convertToUtf8($order);
						$orderData = $this->prepareOrderForMRPacketApi($encodedObject);

						$this->sendOrderToMRPacket($orderData);
					} catch (Exception $e) {
						$this->plugin->helper->messages['error'][] = __('Error Message: Error while preparing package/oder data for API-call:', 'mrpacket') . $e->getMessage();
						$this->plugin->helper->writeLog('Error Message: Error while preparing package/oder data for API-call:'  . $e->getMessage(), 'error');
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

		$mrpacketOrderStatus = get_option('orderstatus', array('wc-processing'));
		if (!is_array($mrpacketOrderStatus) || $mrpacketOrderStatus[0] == '999') {
			return;
		}

		$trackingTableName = MRPACKET_TABLE_TRACKING;
		$ordersToSubmit = $wpdb->get_results("(SELECT {$wpdb->prefix}posts.ID 
												FROM {$wpdb->prefix}posts 
												LEFT JOIN {$trackingTableName} ON {$trackingTableName}.orderId = {$wpdb->prefix}posts.ID
													WHERE {$wpdb->prefix}posts.post_status IN ('" . implode("','", $mrpacketOrderStatus) . "') 
														AND UNIX_TIMESTAMP({$wpdb->prefix}posts.post_date) >= UNIX_TIMESTAMP('" . $pluginInstallationDateTimestamp . "')
														AND UNIX_TIMESTAMP({$wpdb->prefix}posts.post_date) < CURRENT_TIMESTAMP
														AND {$wpdb->prefix}posts.post_type = 'shop_order'
														AND {$trackingTableName}.orderId IS NULL
												GROUP BY {$wpdb->prefix}posts.ID
											) UNION (
												SELECT {$wpdb->prefix}wc_orders.ID FROM {$wpdb->prefix}wc_orders
												LEFT JOIN {$trackingTableName} ON {$trackingTableName}.orderId = {$wpdb->prefix}wc_orders.ID
													WHERE {$wpdb->prefix}wc_orders.status IN ('" . implode("','", $mrpacketOrderStatus) . "') 
														AND UNIX_TIMESTAMP({$wpdb->prefix}wc_orders.date_created_gmt) >= UNIX_TIMESTAMP('" . $pluginInstallationDateTimestamp . "')
														AND UNIX_TIMESTAMP({$wpdb->prefix}wc_orders.date_created_gmt) < CURRENT_TIMESTAMP
														AND {$wpdb->prefix}wc_orders.type = 'shop_order'
														AND {$trackingTableName}.orderId IS NULL
												GROUP BY {$wpdb->prefix}wc_orders.ID
											)");

		return $ordersToSubmit;
	}

	public function getOrdersToSendToMRPacket()
	{
		if (is_array($this->ordersToReSubmit) && count($this->ordersToReSubmit) > 0) {
			$ordersToSubmit = $this->ordersToReSubmit;
		} else {
			$ordersToSubmit = $this->getAllNewOrdersToSubmit();
		}

		if (is_array($ordersToSubmit) && count($ordersToSubmit) > 0) {
			$orders = [];
			foreach ($ordersToSubmit as $orderId) {
				try {
					$orders[] = wc_get_order($orderId);
				} catch (\UnexpectedValueException $ex) {
					if ($ex->getCode() == 0) {
						$this->plugin->helper->db->delete(MRPACKET_TABLE_TRACKING, array('orderId' => $orderId));
						$this->plugin->helper->messages['warning'][] = 'Order with ID: "' . $orderId . '" skipped! Reason: The order no longer exists in the shop-system!';
					}
				}
			}

			return $orders;
		}

		return false;
	}

	public function setDefaults()
	{
		$orderData = [];
		$orderData['receiver']['company'] 		= '';
		$orderData['receiver']['firstname'] 	= '';
		$orderData['receiver']['lastname'] 		= '';
		$orderData['receiver']['street'] 		= '';
		$orderData['receiver']['street_number']	= '';
		$orderData['receiver']['zip']			= '';
		$orderData['receiver']['city'] 			= '';
		$orderData['receiver']['phone_nr'] 		= '';
		$orderData['receiver']['email'] 		= '';
		$orderData['receiver']['country'] 		= '';

		$orderData['shipper']['street'] 		= get_option('woocommerce_store_address');
		$orderData['shipper']['street_number']	= get_option('woocommerce_store_address_2');
		$orderData['shipper']['zip']			= get_option('woocommerce_store_postcode');
		$orderData['shipper']['city'] 			= get_option('woocommerce_store_city');

		$countryAndState = get_option('woocommerce_default_country');
		$countryAndState = explode(":", $countryAndState);
		$orderData['shipper']['country'] 		= $countryAndState[0];

		$orderData['packet']['length']			= null;
		$orderData['packet']['width']			= null;
		$orderData['packet']['height']			= null;
		$orderData['packet']['weight']			= null;
		$orderData['packet']['meta']			= [];

		$orderData['products'] = [];

		return $orderData;
	}

	public function prepareOrderForMRPacketApi($order)
	{
		$orderData = $this->setDefaults();
		$unitsConvertor = new Convertor();
		$orderData['receiver']['firstname']		= $order->get_shipping_first_name();
		$orderData['receiver']['lastname']		= $order->get_shipping_last_name();
		$orderData['receiver']['company']		= $order->get_shipping_company();
		$orderData['receiver']['phone']			= $order->get_billing_phone();
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
		$orderData['receiver']['zip']			= $order->get_shipping_postcode();
		$orderData['receiver']['city']			= $order->get_shipping_city();
		$orderData['receiver']['country']		= $order->get_shipping_country();

		$orderData['packet']['meta']		= ['id' => $order->get_id(), 'reference' => $order->get_order_key()];

		$productCount = 0;
		$orderPositions = $order->get_items() ? $order->get_items() : 0;
		foreach ($orderPositions as $position) {
			if ($this->plugin->helper->ignoreOrderItem($position)) {
				continue;
			}

			for ($i = 0; $i < $position->get_quantity(); $i++) {
				$from_unit_dimension = strtolower(get_option('woocommerce_dimension_unit', 'cm'));
				if (($position->get_product()->get_length() != null) && ($position->get_product()->get_length() > 0)) {
					$unitsConvertor->from($position->get_product()->get_length(), $from_unit_dimension);
					$lengthInCm = $unitsConvertor->to('cm');
					$orderData['products'][$productCount]['length']	= $lengthInCm  ?  $lengthInCm : null;
				}

				if (($position->get_product()->get_width() != null) && ($position->get_product()->get_width() > 0)) {
					$unitsConvertor->from($position->get_product()->get_width(), $from_unit_dimension);
					$widthInCm = $unitsConvertor->to('cm');
					$orderData['products'][$productCount]['width']	= $widthInCm   ?  $widthInCm  : null;
				}

				if (($position->get_product()->get_height() != null) && ($position->get_product()->get_height() > 0)) {
					$unitsConvertor->from($position->get_product()->get_height(), $from_unit_dimension);
					$heighthInCm = $unitsConvertor->to('cm');
					$orderData['products'][$productCount]['height']	= $heighthInCm  ?  $heighthInCm : null;
				}

				$weightInGram = false;
				if (($position->get_product()->get_weight() != null) && ($position->get_product()->get_weight() > 0)) {
					$from_unit_weight = strtolower(get_option('woocommerce_weight_unit', 'kg'));
					if ($from_unit_weight == 'lbs') {
						$from_unit_weight = 'lb';
					}
					$unitsConvertor->from($position->get_product()->get_weight(), $from_unit_weight);
					$weightInGram = $unitsConvertor->to('g');
				}
				$orderData['products'][$productCount]['weight']	= $weightInGram ? $weightInGram : 0;

				$productCount++;
			}
		}

		if (!is_array($orderData['products'])) {
			return;
		}

		$orderData['packet']['length']	= 0;
		$orderData['packet']['width']	= 0;
		$orderData['packet']['height'] = 0;

		if ($productCount == 1) {
			$orderData['packet']['length']	= $orderData['products'][0]['length'];
			$orderData['packet']['width']	= $orderData['products'][0]['width'];
			$orderData['packet']['height'] = $orderData['products'][0]['height'];
		}

		foreach ($orderData['products'] as $product) {
			$orderData['packet']['weight'] += $product['weight'];
		}

		return $orderData;
	}

	public function sendOrderToMRPacket($orderData)
	{
		try {
			$contract = new Contract(
				$this->plugin->helper->getPluginInfo()['shopFrameWorkName'],
				$this->plugin->helper->getPluginInfo()['shopFrameWorkVersion'],
				$this->plugin->helper->getPluginInfo()['shopModuleVersion'],
				$this->plugin->helper->getMRPacketApiToken()
			);

			$requestObj = new ContractPacket();
			$requestObj->receiver['firstname'] 		= $orderData['receiver']['firstname'];
			$requestObj->receiver['lastname']		= $orderData['receiver']['lastname'];
			$requestObj->receiver['company']		= $orderData['receiver']['company'];
			$requestObj->receiver['street']			= $orderData['receiver']['street'];
			$requestObj->receiver['street_number']	= $orderData['receiver']['street_number'];
			$requestObj->receiver['zip']			= $orderData['receiver']['zip'];
			$requestObj->receiver['city']			= $orderData['receiver']['city'];
			$requestObj->receiver['phone_nr']		= $orderData['receiver']['phone_nr'];
			$requestObj->receiver['email']			= $orderData['receiver']['email'];
			$requestObj->receiver['country']		= $orderData['receiver']['country'];

			$requestObj->shipper['street']			= $orderData['shipper']['street'];
			$requestObj->shipper['street_number']	= $orderData['shipper']['street_number'];
			$requestObj->shipper['zip']				= $orderData['shipper']['zip'];
			$requestObj->shipper['city']			= $orderData['shipper']['city'];

			$requestObj->packet['length'] 	= $orderData['packet']['length'];
			$requestObj->packet['width'] 	= $orderData['packet']['width'];
			$requestObj->packet['height'] 	= $orderData['packet']['height'];
			$requestObj->packet['weight'] 	= $orderData['packet']['weight'];
			$requestObj->packet['meta'] 	= $orderData['packet']['meta'];

			$this->status = $contract->create($requestObj);
			if ($this->status['success']) {
				$meta = $this->status['data']['meta'];

				if (!array_key_exists('id', $meta)) {
					throw new \Exception('Fehler beim Export ');
				}

				$mrpacketOrderId = $meta['id'];
				if (is_array($this->ordersToReSubmit) && count($this->ordersToReSubmit) > 0) {
					foreach ($this->ordersToReSubmit as $id => $orderId) {
						if ($mrpacketOrderId != $orderId) {
							continue;
						}

						$this->plugin->helper->archivePacket($id);
					}
				}

				$dateCreated = new DateTime();
				$dateChanged = new DateTime();
				$timeZone = $this->plugin->helper->getDefaultTimezoneString();
				$this->plugin->helper->db->insert(MRPACKET_TABLE_TRACKING, array(
					'pk' 				=> $this->status['data']['id'],
					'orderId' 			=> $mrpacketOrderId,
					'orderReference'	=> $meta['reference'],
					'orderStatus'		=> __('Exported', 'mrpacket'),
					'dCreated'			=> $dateCreated->setTimezone(new DateTimeZone($timeZone))->format('Y-m-d H:i:s'),
					'dChanged'			=> $dateChanged->setTimezone(new DateTimeZone($timeZone))->format('Y-m-d H:i:s'),
					'archive'           => 0,
				));

				$orderIdMsg = (int) $mrpacketOrderId;
				$this->plugin->helper->messages['success'][] = __('Successfully created parcel. Order ID: ', 'mrpacket') . $orderIdMsg;

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
				$this->plugin->helper->resetCrSettingsApiToken();
				$this->plugin->helper->messages['error'][] = __('Your Token seems to be invalid. Please go to the WooCommerce > Settings > MRPacket plugin settings dialogue and request a new one by entering your password and username again.', 'mrpacket');
				$this->plugin->helper->writeLog($this->plugin->helper->messages['error'], 'error');
			} else {

				$errorMsgException = 'Exception: error in communication with mrpacket server: ' . $e->getMessage();
				$errorMsgTrace  = 'Code: '  . $e->getCode() . "<br/>\n";
				$errorMsgTrace .= 'Trace: ' . $e->getTraceAsString();

				$this->plugin->helper->messages['error'][] = $errorMsgException;
			}

			$this->plugin->helper->writeLog($errorMsgException, 'error');
			$this->plugin->helper->writeLog($errorMsgTrace, 'error');
		} catch (Exception $e) {
			$errorMsgException = "Something else went terribly wrong: " . $e->getMessage();
			$errorMsgTrace = 'Trace: ' . $e->getTraceAsString();

			$this->plugin->helper->messages['error'][] = $errorMsgException;

			$this->plugin->helper->writeLog($errorMsgException, 'error');
			$this->plugin->helper->writeLog($errorMsgTrace, 'error');
		}
	}
}
