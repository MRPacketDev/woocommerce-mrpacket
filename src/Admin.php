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

class Admin
{
	protected $plugin;
	protected $db;
	public $helper;

	public function __construct(Plugin $plugin)
	{
		global $wpdb;

		$this->db     = $wpdb;
		$this->plugin = $plugin;

		$this->helper = new MRPacketHelper($plugin);
	}

	public function inPluginContext()
	{
		$screen = get_current_screen();
		if ((strpos($screen->id, 'mrpacket') === false) && (strpos($screen->id, 'woocommerce_page_wc-settings') === false)) {
			return false;
		}

		return true;
	}

	public function enqueue_styles()
	{

		if (!$this->inPluginContext()) {
			return;
		}

		\wp_register_style($this->plugin->get_name() . '-admin', \plugin_dir_url(dirname(__FILE__)) . 'assets/scss/mrpacket.css', array(), $this->plugin->get_version());
		\wp_enqueue_style($this->plugin->get_name() . '-admin');
	}

	public function enqueue_scripts()
	{
		if (!$this->inPluginContext()) {
			return;
		}

		\wp_register_script(
			$this->plugin->get_name() . '-admin-bootstrap',
			\plugin_dir_url(dirname(__FILE__)) . 'assets/js/bootstrap4.min.js',
			array('jquery'),
			$this->plugin->get_version(),
			false
		);

		\wp_register_script(
			$this->plugin->get_name() . '-admin-datatable',
			\plugin_dir_url(dirname(__FILE__)) . 'assets/js/jquery.dataTables.min.js',
			array('jquery'),
			$this->plugin->get_version(),
			false
		);

		\wp_register_script(
			$this->plugin->get_name() . '-admin-select',
			\plugin_dir_url(dirname(__FILE__)) . 'assets/js/dataTables.select.min.js',
			array('jquery'),
			$this->plugin->get_version(),
			false
		);

		\wp_register_script(
			$this->plugin->get_name() . '-admin',
			\plugin_dir_url(dirname(__FILE__)) . 'assets/js/plugin-admin.min.js',
			array('jquery'),
			$this->plugin->get_version(),
			false
		);

		\wp_localize_script(
			$this->plugin->get_name() . '-admin',
			__NAMESPACE__ . 'ParamsAdmin',
			array(
				'mrpacket_json_url' 	=> \plugin_dir_url(dirname(__FILE__)) . 'assets/json/German.json',
				'mrpacket_ajax_url' 	=> admin_url('admin-ajax.php'),
				'security' 		=> wp_create_nonce("plugin-security"),
			)
		);

		\wp_enqueue_script($this->plugin->get_name() . '-admin-bootstrap');
		\wp_enqueue_script($this->plugin->get_name() . '-admin-datatable');
		\wp_enqueue_script($this->plugin->get_name() . '-admin-select');
		\wp_enqueue_script($this->plugin->get_name() . '-admin');
	}

	function add_action_links($links)
	{

		$links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=mrpacket_tab') . '">' . __('MRPacket Settings', 'mrpacket') . '</a>';
		return $links;
	}


	public function mrpacket_plugin_menu()
	{

		$notification_count = false;
		if (!$this->helper->getMRPacketApiToken()) {
			$notification_count = 1;
		}

		add_menu_page(
			__('MRPacket Tracking', 'mrpacket'),
			__('MRPacket Tracking', 'mrpacket'),
			'edit_mrpacket',
			'mrpacket',
			array(
				$this,
				'mrpacket_tracking_list'
			),
			'dashicons-editor-ul',
			6
		);

		add_submenu_page(
			'mrpacket',
			__('MRPacket Settings', 'mrpacket'),
			$notification_count ? __('MRPacket Settings', 'mrpacket') . ' ' . sprintf(' <span class="awaiting-mod">%d</span>', $notification_count) : __('MRPacket Settings', 'mrpacket'),
			'edit_mrpacket',
			admin_url('admin.php?page=wc-settings&tab=mrpacket_tab')
		);

		add_submenu_page(
			'mrpacket',
			__('Logs', 'mrpacket'),
			__('Logs', 'mrpacket'),
			'read',
			'mrpacket_logs',
			array(
				$this,
				'mrpacket_logging_list'
			)
		);
	}

	public function mrpacket_tracking_list()
	{
		if (!current_user_can('edit_mrpacket')) {
			__("You dont have permission to manage options. Please contact site administrator. You need the capability (access right) \'edit_mrpacket\'.", 'mrpacket');
			return;
		}

		$token = $this->helper->getMRPacketApiToken();

		do_action('show_mrpacket_notices', $this->helper->messages);

		if (isset($_REQUEST['mrpacket_tracking_form_nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['mrpacket_tracking_form_nonce'])), 'mrpacket_tracking')) {
			$this->helper->messages['error'][] = __('Sorry, your nonce was not correct. Please try again.', 'mrpacket');
		} else {
			$isRefresh = isset($_POST['refresh']) ? (int) $_POST['refresh'] : 0;
			if ($isRefresh === 1) {
				do_action('mrpacket_cron_event');
			}
		}

		$adminURL = $this->helper->pluginAdminUrl;
		$mrpacketData = $this->db->get_results("SELECT * FROM " . MRPACKET_TABLE_TRACKING . " WHERE `archive` != 1 ORDER BY dCreated DESC");
		require_once ((dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'page.backend.tracking.php';
	}

	public function mrpacket_logging_list()
	{
		if (isset($_REQUEST['mrpacket_logging_table_nonce']) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['mrpacket_logging_table_nonce'])), 'mrpacket_logging')) {
			$this->helper->messages['error'][] = __('Sorry, your nonce was not correct. Please try again.', 'mrpacket');
		} else {
			$isDownload = isset($_POST['download']) ? (int) $_POST['download'] : 0;
			$isClear = isset($_POST['clear']) ? (int) $_POST['clear'] : 0;
			if ($isDownload === 1) {
				$this->helper->messages['info'][] = __('Downloading Logs..', 'mrpacket');
				$loggingData = $this->db->get_results("SELECT * FROM " . MRPACKET_TABLE_LOGGING . " ORDER BY ID DESC", ARRAY_A);

				$csv_content = '';
				if (!empty($loggingData)) {
					$csv_content .= implode(",", array_keys(reset($loggingData))) . "\n";
					foreach ($loggingData as $row) {
						$csv_content .= implode(",", $row) . "\n";
					}
				}

				// Save to a temporary file
				$upload_dir = wp_upload_dir();
				$tempFile = $upload_dir['basedir'] . '/export_mrp_logs_' . gmdate('Y-m-d_H-i-s') . '.csv';

				global $wp_filesystem;
				$wp_filesystem->put_contents($tempFile, $csv_content);

				// Serve the file for download
				ob_clean();
				header('Content-Type: text/csv');
				header('Content-Disposition: attachment; filename="' . basename($tempFile) . '"');
				header('Content-Length: ' . filesize($tempFile));
				$file_content = $wp_filesystem->get_contents($tempFile);
				echo $file_content;
				wp_delete_file($tempFile);

				exit;
			} else if ($isClear === 1) {
				$this->helper->messages['info'][] = __('Clearing Logs..', 'mrpacket');
				$this->db->get_results("DELETE FROM " . MRPACKET_TABLE_LOGGING);
			}
		}

		$this->helper->showNotices();

		$adminURL = admin_url('admin.php?page=mrpacket_logs');
		$loggingData = $this->db->get_results("SELECT * FROM " . MRPACKET_TABLE_LOGGING . " ORDER BY ID DESC LIMIT 100");
		require_once ((dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'page.backend.logging.php';
	}
}
