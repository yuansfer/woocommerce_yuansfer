<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class that handles Alipay payment method.
 *
 * @extends WC_Gateway_Yuansfer
 *
 */
class WC_Gateway_Yuansfer_Alipay extends WC_Yuansfer_Payment_Gateway {
    const VENDOR = 'alipay';
    const ICON = 'alipay';

    protected $settle_currency_for_cny = 'USD';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                      = 'yuansfer_alipay';
		$this->method_title            = __('Yuansfer Alipay', 'woocommerce-yuansfer');
        $this->settle_currency_for_cny = $this->get_option('settle_currency');

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

		$icons_str .= $icons['alipay'];

		return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
	}

	/**
	 * Payment_scripts function.
	 */
	public function payment_scripts() {
		if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page()) {
			return;
		}

		wp_enqueue_style('yuansfer_styles');
		wp_enqueue_script('woocommerce_yuansfer');
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require(WC_YUANSFER_PLUGIN_PATH . '/includes/admin/yuansfer-alipay-settings.php');
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
			id="yuansfer-alipay-payment-data"
			data-amount="' . esc_attr(WC_Yuansfer_Helper::get_yuansfer_amount($total)) . '"
			data-currency="' . esc_attr(strtolower(get_woocommerce_currency())) . '">';

		if ($description) {
			echo apply_filters('wc_yuansfer_description', wpautop(wp_kses_post($description)), $this->id);
		}

		echo '</div>';
	}

    public function get_supported_currency() {
        return apply_filters('wc_yuansfer_supported_currencies', array(
            'USD', 'CNY', 'PHP', 'IDR', 'KRW', 'HKD', 'GBP'
        ));
    }

	/**
	 * Creates the source for charge.
	 *
	 * @param object $order
	 * @return mixed
	 */
	public function create_source($order) {
		if($order->has_status(array('failed'))) {
			$order->update_status('pending',"Initialize new Yuansfer Payment");
		}
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
            throw new WC_Yuansfer_Exception('Alipay only support "' . implode('", "', $supportedCurrency). '" for currency');
        }

        $settleCurrency = 'USD';
        $amount = WC_Yuansfer_Helper::get_yuansfer_amount($order->get_total(), $currency);

        switch ($currency) {
            case 'PHP':
                if ($amount < 1) {
                    throw new WC_Yuansfer_Exception('The minimum value is 1PHP');
                }
                break;

            case 'IDR':
                if ($amount < 300) {
                    throw new WC_Yuansfer_Exception('The minimum value is 300IDR');
                }
                break;

            case 'KRW':
                if ($amount < 50) {
                    throw new WC_Yuansfer_Exception('The minimum value is 50KRW');
                }
                break;

            case 'HKD':
                if ($amount < 0.1) {
                    throw new WC_Yuansfer_Exception('The minimum value is 0.1HKD');
                }
                break;

            case 'GBP':
                $settleCurrency = 'GBP';
                break;

            case 'CNY':
                if (in_array($this->settle_currency_for_cny, array('USD', 'GBP'), true)) {
                    $settleCurrency = $this->settle_currency_for_cny;
                }
                break;
        }

        $post_data['amount']         = $amount;
		$post_data['currency']       = $currency;
        $post_data['settleCurrency'] = $settleCurrency;
		$post_data['vendor']         = 'alipay';
        $post_data['reference']      = $order_id . ':' . uniqid('alipay:');
        $post_data['ipnUrl']         = WC_Yuansfer_Helper::get_webhook_url();
		$post_data['callbackUrl']    = $return_url;
		$post_data['terminal']       = $this->get_terminal();

        if ($post_data['terminal'] === 'WAP') {
            $post_data['osType'] = $this->detect->is('iOS') ? 'IOS' : 'ANDROID';
        }

		if (!empty($this->statement_descriptor)) {
			$post_data['description'] = WC_Yuansfer_Helper::clean_statement_descriptor($this->statement_descriptor);
		}

		$order->update_meta_data('_yuansfer_settle_currency', $post_data['settleCurrency']);
		$order->save();

		WC_Yuansfer_Logger::log('Info: Begin creating Alipay source');

		return WC_Yuansfer_API::request(apply_filters('wc_yuansfer_alipay_source', $post_data, $order), WC_Yuansfer_API::SECURE_PAY);
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

			WC_Yuansfer_Logger::log('Info: Redirecting to Alipay...');

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
