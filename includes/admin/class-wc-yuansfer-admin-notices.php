<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class that represents admin notices.
 */
class WC_Yuansfer_Admin_Notices {
	/**
	 * Notices (array)
	 * @var array
	 */
	public $notices = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action('admin_notices', array($this, 'admin_notices'));
		add_action('wp_loaded', array($this, 'hide_notices'));
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 */
	public function add_admin_notice($slug, $class, $message, $dismissible = false) {
		$this->notices[$slug] = array(
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		);
	}

	/**
	 * Display any notices we've collected thus far.
	 */
	public function admin_notices() {
		if (!current_user_can('manage_woocommerce')) {
			return;
		}

		// Main Yuansfer payment method.
		$this->yuansfer_check_environment();

		// All other payment methods.
		$this->payment_methods_check_environment();

		foreach ((array)$this->notices as $notice_key => $notice) {
			echo '<div class="' . esc_attr($notice['class']) . '" style="position:relative;">';

			if ($notice['dismissible']) {
			?>
				<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('wc-yuansfer-hide-notice', $notice_key), 'wc_yuansfer_hide_notices_nonce', '_wc_yuansfer_notice_nonce')); ?>" class="woocommerce-message-close notice-dismiss" style="position:absolute;right:1px;padding:9px;text-decoration:none;"></a>
			<?php
			}

			echo '<p>';
			echo wp_kses($notice['message'], array('a' => array('href' => array())));
			echo '</p></div>';
		}
	}

	/**
	 * List of available payment methods.
	 *
	 * @return array
	 */
	public function get_payment_methods() {
		return array(
			'Alipay'        => 'WC_Gateway_Yuansfer_Alipay',
            'WechatPay'     => 'WC_Gateway_Yuansfer_Wechatpay',
		);
	}

	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the environment changes after activation. Also handles upgrade routines.
	 */
	public function yuansfer_check_environment() {
		$show_ssl_notice    = get_option('wc_yuansfer_show_ssl_notice');
		$show_keys_notice   = get_option('wc_yuansfer_show_keys_notice');
		$show_phpver_notice = get_option('wc_yuansfer_show_phpver_notice');
		$show_wcver_notice  = get_option('wc_yuansfer_show_wcver_notice');
		$show_curl_notice   = get_option('wc_yuansfer_show_curl_notice');
		$options            = get_option('woocommerce_yuansfer_settings');

		if (isset($options['enabled']) && 'yes' === $options['enabled']) {
			if (empty($show_phpver_notice)) {
				if (version_compare(phpversion(), WC_YUANSFER_MIN_PHP_VER, '<')) {
					/* translators: 1) int version 2) int version */
					$message = __('WooCommerce Yuansfer - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-yuansfer');

					$this->add_admin_notice('phpver', 'error', sprintf($message, WC_YUANSFER_MIN_PHP_VER, phpversion()), true);

					return;
				}
			}

			if (empty($show_wcver_notice)) {
				if (version_compare(WC_VERSION, WC_YUANSFER_MIN_WC_VER, '<')) {
					/* translators: 1) int version 2) int version */
					$message = __('WooCommerce Yuansfer - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-yuansfer');

					$this->add_admin_notice('wcver', 'notice notice-warning', sprintf($message, WC_YUANSFER_MIN_WC_VER, WC_VERSION), true);

					return;
				}
			}

			if (empty($show_curl_notice)) {
				if (!function_exists('curl_init')) {
					$this->add_admin_notice('curl', 'notice notice-warning', __('WooCommerce Yuansfer - cURL is not installed.', 'woocommerce-yuansfer'), true);
				}
			}

			if (empty($show_keys_notice)) {
				$secret = WC_Yuansfer_API::get_api_token();

				if (empty($secret) && !(isset($_GET['page'], $_GET['section']) && 'wc-settings' === $_GET['page'] && 'yuansfer' === $_GET['section'])) {
					$setting_link = $this->get_setting_link();
					$this->add_admin_notice('keys', 'notice notice-warning', sprintf(__('Yuansfer is almost ready. To get started, <a href="%s">set your Yuansfer API token</a>.', 'woocommerce-yuansfer'), $setting_link), true);
				}
			}

			if (empty($show_ssl_notice)) {
				// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
				if (!wc_checkout_is_https()) {
					$this->add_admin_notice('ssl', 'notice notice-warning', sprintf(__('Yuansfer is enabled, but a SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'woocommerce-yuansfer'), 'https://en.wikipedia.org/wiki/Transport_Layer_Security'), true);
				}
			}
		}
	}

	/**
	 * Environment check for all other payment methods.
	 */
	public function payment_methods_check_environment() {
		$payment_methods = $this->get_payment_methods();

		foreach ($payment_methods as $method => $class) {
			$show_notice = get_option('wc_yuansfer_show_' . strtolower($method) . '_notice');
			$gateway     = new $class();

			if ('yes' !== $gateway->enabled || 'no' === $show_notice) {
				continue;
			}

			if (!in_array(get_woocommerce_currency(), $gateway->get_supported_currency())) {
				$this->add_admin_notice($method, 'notice notice-error', sprintf(__('%s is enabled - it requires store currency to be set to %s', 'woocommerce-yuansfer'), $method, implode(', ', $gateway->get_supported_currency())), true);
			}
		}
	}

	/**
	 * Hides any admin notices.
	 */
	public function hide_notices() {
		if (isset($_GET['wc-yuansfer-hide-notice']) && isset($_GET['_wc_yuansfer_notice_nonce'])) {
			if (!wp_verify_nonce($_GET['_wc_yuansfer_notice_nonce'], 'wc_yuansfer_hide_notices_nonce')) {
				wp_die(__('Action failed. Please refresh the page and retry.', 'woocommerce-yuansfer'));
			}

			if (!current_user_can('manage_woocommerce')) {
				wp_die(__('Cheatin&#8217; huh?', 'woocommerce-yuansfer'));
			}

			$notice = wc_clean($_GET['wc-yuansfer-hide-notice']);

			switch ($notice) {
				case 'phpver':
					update_option('wc_yuansfer_show_phpver_notice', 'no');
					break;
				case 'wcver':
					update_option('wc_yuansfer_show_wcver_notice', 'no');
					break;
				case 'curl':
					update_option('wc_yuansfer_show_curl_notice', 'no');
					break;
				case 'ssl':
					update_option('wc_yuansfer_show_ssl_notice', 'no');
					break;
				case 'keys':
					update_option('wc_yuansfer_show_keys_notice', 'no');
					break;
				case 'Alipay':
					update_option('wc_yuansfer_show_alipay_notice', 'no');
					break;
                case 'WechatPay':
                    update_option('wc_yuansfer_show_wechatpay_notice', 'no');
                    break;

                case 'CreditCard':
                    update_option('wc_yuansfer_show_creditcard_notice', 'no');
                    break;
			}
		}
	}

	/**
	 * Get setting link.
	 *
	 *
	 * @return string Setting link
	 */
	public function get_setting_link() {
		$use_id_as_section = function_exists('WC') ? version_compare(WC()->version, '2.6', '>=') : false;

		$section_slug = $use_id_as_section ? 'yuansfer' : strtolower('WC_Gateway_Yuansfer');

		return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
	}
}

new WC_Yuansfer_Admin_Notices();
