<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class that handles WeChat Pay payment method.
 *
 * @extends WC_Gateway_Yuansfer
 *
 */
class WC_Gateway_Yuansfer_Wechatpay extends WC_Yuansfer_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'yuansfer_wechatpay';
		$this->method_title         = __('Yuansfer WeChat Pay', 'woocommerce-yuansfer');

		parent::__construct();
	}

	/**
	 * Get_icon function.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icons = $this->payment_icons();

		$icons_str = '';

		$icons_str .= $icons['wechatpay'];

		return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
	}

	/**
	 * Payment_scripts function.
	 */
	public function payment_scripts() {
		if (!is_cart() && ! is_checkout() && !isset($_GET['pay_for_order']) && ! is_add_payment_method_page()) {
			return;
		}

		wp_enqueue_style('yuansfer_styles');
		wp_enqueue_script('woocommerce_yuansfer');
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require(WC_YUANSFER_PLUGIN_PATH . '/includes/admin/yuansfer-wechatpay-settings.php');
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$user        = wp_get_current_user();
		$total       = WC()->cart->total;
		$description = $this->get_description();

		// If paying from order, we need to get total from order not cart.
		if (isset($_GET['pay_for_order']) && !empty($_GET['key'])) {
			$order = wc_get_order(wc_get_order_id_by_order_key(wc_clean($_GET['key'])));
			$total = $order->get_total();
		}

		if (is_add_payment_method_page()) {
			$total        = '';
		}

		echo '<div
			id="yuansfer-wechatpay-payment-data"
			data-amount="' . esc_attr(WC_Yuansfer_Helper::get_yuansfer_amount($total)) . '"
			data-currency="' . esc_attr(strtolower(get_woocommerce_currency())) . '">';

		if ($description) {
			echo apply_filters('wc_yuansfer_description', wpautop(wp_kses_post($description)), $this->id);
		}

		echo '</div>';
	}

	/**
	 * Creates the source for charge.
	 *
	 * @param object $order
	 * @return mixed
	 */
    public function create_source($order) {
        $currency                 = $order->get_currency();
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }
        $order_id                 = $order->get_id();
        $return_url               = $this->get_yuansfer_return_url($order);
        $post_data                = array();
        $post_data['merchantNo']  = $this->merchant_no;
        $post_data['storeNo']     = $this->store_no;
        $currency = strtoupper($currency);
        $supportedCurrency = $this->get_supported_currency();
        if (!in_array($currency, $supportedCurrency, true)) {
            throw new WC_Yuansfer_Exception('WeChat Pay only support "' . implode('", "', $supportedCurrency). '" for currency');
        }

        $post_data['amount']      = WC_Yuansfer_Helper::get_yuansfer_amount($order->get_total(), $currency);
        $post_data['currency']    = $currency;
        $post_data['settleCurrency'] = 'USD';
        $post_data['vendor']      = 'wechatpay';
        $post_data['reference']   = $order_id . ':' . uniqid('wechatpay:');
        $post_data['ipnUrl']      = WC_Yuansfer_Helper::get_webhook_url();
        $post_data['callbackUrl'] = $return_url;
        $post_data['terminal']    = $this->get_terminal(true);

        if ($post_data['terminal'] === 'WAP' || $post_data['terminal'] === 'MWEB') {
            $post_data['osType'] = $this->detect->is('iOS') ? 'IOS' : 'ANDROID';
        }

        if (!empty($this->statement_descriptor)) {
            $post_data['description'] = WC_Yuansfer_Helper::clean_statement_descriptor($this->statement_descriptor);
		}
		
		// $order->update_meta_data('_yuansfer_settle_currency', $post_data['settleCurrency']);
		// $order->save();

        WC_Yuansfer_Logger::log('Info: Begin creating WeChat Pay source');

        return WC_Yuansfer_API::request(apply_filters('wc_yuansfer_wechatpay_source', $post_data, $order), WC_Yuansfer_API::SECURE_PAY);
    }

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force payment source to be saved.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array
	 */
    public function process_payment($order_id, $retry = true, $force_save_source = false) {
        try {
            $order = wc_get_order($order_id);

            // This will throw exception if not valid.
            $this->validate_minimum_order_amount($order);

            $response = $this->create_source($order);

            if (empty($response->ret_code) || $response->ret_code !== '000100') {
                $order->add_order_note($response->ret_msg);

                throw new WC_Yuansfer_Exception($response->ret_msg);
            }

            WC_Yuansfer_Logger::log('Info: Redirecting to Wechat Pay...');

            return array(
                'result'   => 'success',
                'redirect' => $response->result->cashierUrl,
            );
        } catch (WC_Yuansfer_Exception $e) {
            wc_add_notice($e->getLocalizedMessage(), 'error');
            WC_Yuansfer_Logger::log('Error: ' . $e->getMessage());

            do_action('wc_gateway_yuansfer_process_payment_error', $e, $order);

            $statuses = array('pending', 'failed');

            if ($order->has_status($statuses)) {
                $this->send_failed_order_email($order_id);
            }

            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }
}
