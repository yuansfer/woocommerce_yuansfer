<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * WC_Gateway_Yuansfer class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Yuansfer extends WC_Yuansfer_Payment_Gateway {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'yuansfer';
		$this->method_title         = __( 'Yuansfer China UnionPay', 'woocommerce-yuansfer' );

		parent::__construct();

        add_action('admin_enqueue_yuansfer', array($this, 'admin_scripts'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	}


	/**
	 * Adds a notice for customer when they update their billing address.
	 *
	 * @param int $user_id
	 * @param array $load_address
	 */
	public function show_update_card_notice($user_id, $load_address) {
		if (!WC_Yuansfer_Payment_Tokens::customer_has_saved_methods($user_id) || 'billing' !== $load_address) {
			return;
		}

		/* translators: 1) Opening anchor tag 2) closing anchor tag */
		wc_add_notice(sprintf(__('If your billing address has been changed for saved payment methods, be sure to remove any %1$ssaved payment methods%2$s on file and re-add them.', 'woocommerce-yuansfer'), '<a href="' . esc_url(wc_get_endpoint_url('payment-methods')) . '" class="wc-yuansfer-update-card-notice" style="text-decoration:underline;">', '</a>'), 'notice');
	}

	/**
	 * Get_icon function.
     *
	 * @return string
	 */
	public function get_icon() {
		$icons = $this->payment_icons();
        $icons_str = $icons['unionpay'];
		return apply_filters('woocommerce_gateway_icon', $icons_str, $this->id);
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = require(dirname(__FILE__) . '/admin/yuansfer-settings.php');
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		$user                 = wp_get_current_user();
		$display_tokenization = false;
		$total                = WC()->cart->total;
		$user_email           = '';
		$description          = $this->get_description() ? $this->get_description() : '';
		$firstname            = '';
		$lastname             = '';

		// If paying from order, we need to get total from order not cart.
		if (isset($_GET['pay_for_order']) && !empty($_GET['key'])) {
			$order      = wc_get_order(wc_get_order_id_by_order_key(wc_clean($_GET['key'])));
			$total      = $order->get_total();
			$user_email = $order->get_billing_email();
		} else {
			if ($user->ID) {
				$user_email = get_user_meta($user->ID, 'billing_email', true);
				$user_email = $user_email ? $user_email : $user->user_email;
			}
		}

		if (is_add_payment_method_page()) {
			$pay_button_text = __('Add Card', 'woocommerce-yuansfer');
			$total           = '';
			$firstname       = $user->user_firstname;
			$lastname        = $user->user_lastname;

		} elseif (function_exists('wcs_order_contains_subscription') && isset($_GET['change_payment_method'])) {
			$pay_button_text = __('Change Payment Method', 'woocommerce-yuansfer');
			$total        = '';
		} else {
			$pay_button_text = '';
		}

		ob_start();

		echo '<div
			id="yuansfer-payment-data"
			data-panel-label="' . esc_attr($pay_button_text) . '"
			data-email="' . esc_attr($user_email) . '"
			data-verify-zip="' . esc_attr(apply_filters('wc_yuansfer_checkout_verify_zip', false) ? 'true' : 'false') . '"
			data-billing-address="' . esc_attr(apply_filters('wc_yuansfer_checkout_require_billing_address', false) ? 'true' : 'false') . '"
			data-shipping-address="' . esc_attr(apply_filters('wc_yuansfer_checkout_require_shipping_address', false) ? 'true' : 'false') . '" 
			data-amount="' . esc_attr(WC_Yuansfer_Helper::get_yuansfer_amount($total)) . '"
			data-name="' . esc_attr($this->statement_descriptor) . '"
			data-full-name="' . esc_attr($firstname . ' ' . $lastname) . '"
			data-currency="' . esc_attr(strtolower(get_woocommerce_currency())) . '"
			data-locale="' . esc_attr(apply_filters('wc_yuansfer_checkout_locale', $this->get_locale())) . '"
			data-allow-remember-me="' . esc_attr(apply_filters('wc_yuansfer_allow_remember_me', true) ? 'true' : 'false') . '">';

		if ($description) {
			if ($this->testmode) {
				/* translators: link to Yuansfer testing page */
				$description .= ' ' . sprintf(__('TEST MODE ENABLED. In test mode, you can check the <a href="%s" target="_blank">Yuansfer sandbox environment documentation</a> for card numbers.', 'woocommerce-yuansfer'), 'https://docs.yuansfer.com/en/#sandbox-environment');
				$description  = trim($description);
			}

			echo apply_filters('wc_yuansfer_description', wpautop(wp_kses_post($description)), $this->id);
		}

		if ($display_tokenization) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		if (apply_filters('wc_yuansfer_display_save_payment_method_checkbox', $display_tokenization) && !is_add_payment_method_page() && !isset($_GET['change_payment_method'])) {
			$this->save_payment_method_checkbox();
		}

		echo '</div>';

		ob_end_flush();
	}

	/**
	 * Load admin scripts.
	 */
	public function admin_scripts() {
		if ('woocommerce_page_wc-settings' !== get_current_screen()->id) {
			return;
		}

		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script('woocommerce_yuansfer_admin', plugins_url('assets/js/yuansfer-admin' . $suffix . '.js', WC_YUANSFER_MAIN_FILE), array(), WC_YUANSFER_VERSION, true);
	}

	/**
	 * Payment_scripts function.
	 *
	 * Outputs scripts used for yuansfer payment
	 */
	public function payment_scripts() {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page()) {
            return;
        }

        wp_enqueue_style('yuansfer_styles');
        wp_enqueue_script('woocommerce_yuansfer');
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
        if (in_array($currency, ['RMB', 'CNY'], true)) {
            $post_data['rmbAmount']      = WC_Yuansfer_Helper::get_yuansfer_amount($order->get_total(), $currency);
        } else {
            $post_data['amount'] = WC_Yuansfer_Helper::get_yuansfer_amount($order->get_total(), $currency);
        }
        $post_data['currency']    = 'USD';
        $post_data['vendor']      = 'unionpay';
        $post_data['reference']   = $order_id . ':' . uniqid('unionpay:');
        $post_data['ipnUrl']      = WC_Yuansfer_Helper::get_webhook_url();
        $post_data['callbackUrl'] = $return_url;
        $post_data['terminal']    = 'ONLINE';

        if (!empty($this->statement_descriptor)) {
            $post_data['description'] = WC_Yuansfer_Helper::clean_statement_descriptor($this->statement_descriptor);
        }

        WC_Yuansfer_Logger::log('Info: Begin creating UnionPay source');

        return WC_Yuansfer_API::request(apply_filters('wc_yuansfer_unionpay_source', $post_data, $order), 'online:secure-pay');
    }

	/**
	 * Process the payment
     *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_save_source Force save the payment source.
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

            if (\strpos($response, 'error') === 0) {
                $order->add_order_note($response);

                throw new WC_Yuansfer_Exception($response, $response);
            }

            $order->update_meta_data('_yuansfer_response', $response);
            $order->save();

            WC_Yuansfer_Logger::log('Info: Redirecting to UnionPay...');

            return array(
                'result'   => 'success',
                'redirect' => WC_Yuansfer_Helper::get_redirect_url($order_id),
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
