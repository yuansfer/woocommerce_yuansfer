<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Provides static methods as helpers.
 */
class WC_Yuansfer_Helper {
	const LEGACY_META_NAME_FEE      = 'Yuansfer Fee';
	const LEGACY_META_NAME_NET      = 'Net Revenue From Yuansfer';
	const META_NAME_FEE             = '_yuansfer_fee';
	const META_NAME_NET             = '_yuansfer_net';
	const META_NAME_YUANSFER_CURRENCY = '_yuansfer_currency';

	/**
	 * Gets the Yuansfer currency for order.
	 *
	 * @param object $order
	 * @return string $currency
	 */
	public static function get_yuansfer_currency($order = null) {
		if ( is_null( $order ) ) {
			return false;
		}

		return $order->get_meta(self::META_NAME_YUANSFER_CURRENCY, true);
	}

	/**
	 * Updates the Yuansfer currency for order.
	 *
	 * @param object $order
	 * @param string $currency
	 */
	public static function update_yuansfer_currency($order = null, $currency) {
		if (is_null($order)) {
			return false;
		}

		$order->update_meta_data(self::META_NAME_YUANSFER_CURRENCY, $currency);
	}

	/**
	 * Gets the Yuansfer fee for order. With legacy check.
	 *
	 * @param object $order
	 * @return string $amount
	 */
	public static function get_yuansfer_fee($order = null) {
		if (is_null($order)) {
			return false;
		}

		$amount = $order->get_meta(self::META_NAME_FEE, true);

		// If not found let's check for legacy name.
		if (empty($amount)) {
			$amount = $order->get_meta(self::LEGACY_META_NAME_FEE, true);

			// If found update to new name.
			if ($amount) {
				self::update_yuansfer_fee( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the Yuansfer fee for order.
	 *
	 * @param object $order
	 * @param float $amount
	 */
	public static function update_yuansfer_fee($order = null, $amount = 0.0) {
		if (is_null($order)) {
			return false;
		}

		$order->update_meta_data(self::META_NAME_FEE, $amount);
	}

	/**
	 * Deletes the Yuansfer fee for order.
	 *
	 * @param object $order
	 */
	public static function delete_yuansfer_fee($order = null) {
		if (is_null($order)) {
			return false;
		}

		$order_id = $order->get_id();

		delete_post_meta($order_id, self::META_NAME_FEE);
		delete_post_meta($order_id, self::LEGACY_META_NAME_FEE);
	}

	/**
	 * Gets the Yuansfer net for order. With legacy check.
	 *
	 * @param object $order
	 * @return string $amount
	 */
	public static function get_yuansfer_net($order = null) {
		if (is_null($order)) {
			return false;
		}

		$amount = $order->get_meta(self::META_NAME_NET, true);

		// If not found let's check for legacy name.
		if (empty($amount)) {
			$amount = $order->get_meta(self::LEGACY_META_NAME_NET, true);

			// If found update to new name.
			if ($amount) {
				self::update_yuansfer_net($order, $amount);
			}
		}

		return $amount;
	}

	/**
	 * Updates the Yuansfer net for order.
	 *
	 * @param object $order
	 * @param float $amount
	 */
	public static function update_yuansfer_net($order = null, $amount = 0.0) {
		if (is_null($order)) {
			return false;
		}

		$order->update_meta_data(self::META_NAME_NET, $amount);
	}

	/**
	 * Deletes the Yuansfer net for order.
	 *
	 * @param object $order
	 */
	public static function delete_yuansfer_net($order = null) {
		if (is_null($order)) {
			return false;
		}

		$order_id = $order->get_id();

		delete_post_meta($order_id, self::META_NAME_NET);
		delete_post_meta($order_id, self::LEGACY_META_NAME_NET);
	}

	/**
	 * Get Yuansfer amount to pay
	 *
	 * @param float  $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_yuansfer_amount($total, $currency = '') {
		if (!$currency) {
			$currency = get_woocommerce_currency();
		}

		if (in_array(strtolower($currency), self::no_decimal_currencies())) {
			return absint($total);
		}

		return wc_format_decimal($total, wc_get_price_decimals());
	}

	/**
	 * Localize Yuansfer messages based on code
	 *
	 * @return array
	 */
	public static function get_localized_messages() {
		return apply_filters('wc_yuansfer_localized_messages', array(
			'invalid_number'           => __('The card number is not a valid credit card number.', 'woocommerce-yuansfer'),
			'invalid_expiry_month'     => __('The card\'s expiration month is invalid.', 'woocommerce-yuansfer'),
			'invalid_expiry_year'      => __('The card\'s expiration year is invalid.', 'woocommerce-yuansfer'),
			'invalid_cvc'              => __('The card\'s security code is invalid.', 'woocommerce-yuansfer'),
			'incorrect_number'         => __('The card number is incorrect.', 'woocommerce-yuansfer'),
			'incomplete_number'        => __('The card number is incomplete.', 'woocommerce-yuansfer'),
			'incomplete_cvc'           => __('The card\'s security code is incomplete.', 'woocommerce-yuansfer'),
			'incomplete_expiry'        => __('The card\'s expiration date is incomplete.', 'woocommerce-yuansfer'),
			'expired_card'             => __('The card has expired.', 'woocommerce-yuansfer'),
			'incorrect_cvc'            => __('The card\'s security code is incorrect.', 'woocommerce-yuansfer'),
			'incorrect_zip'            => __('The card\'s zip code failed validation.', 'woocommerce-yuansfer'),
			'invalid_expiry_year_past' => __('The card\'s expiration year is in the past', 'woocommerce-yuansfer'),
			'card_declined'            => __('The card was declined.', 'woocommerce-yuansfer'),
			'missing'                  => __('There is no card on a customer that is being charged.', 'woocommerce-yuansfer'),
			'processing_error'         => __('An error occurred while processing the card.', 'woocommerce-yuansfer'),
			'invalid_request_error'    => __('Unable to process this payment, please try again or use alternative method.', 'woocommerce-yuansfer'),
			'invalid_sofort_country'   => __('The billing country is not accepted by SOFORT. Please try another country.', 'woocommerce-yuansfer'),
		));
	}

	/**
	 * List of currencies supported by Yuansfer that has no decimals.
	 *
	 * @return array $currencies
	 */
	public static function no_decimal_currencies() {
		return array(

		);
	}

	/**
	 * Yuansfer uses smallest denomination in currencies such as cents.
	 * We need to format the returned currency from Yuansfer into human readable form.
	 * The amount is not used in any calculations so returning string is sufficient.
	 *
	 * @param object $balance_transaction
	 * @param string $type Type of number to format
	 * @return string
	 */
	public static function format_balance_fee($balance_transaction, $type = 'fee') {
		if (!is_object($balance_transaction)) {
			return;
		}

		if (in_array(strtolower($balance_transaction->currency), self::no_decimal_currencies())) {
			if ('fee' === $type) {
				return $balance_transaction->fee;
			}

			return $balance_transaction->net;
		}

		if ('fee' === $type) {
			return number_format($balance_transaction->fee / 100, 2, '.', '');
		}

		return number_format($balance_transaction->net / 100, 2, '.', '');
	}

	/**
	 * Checks Yuansfer minimum order value authorized per currency
	 */
	public static function get_minimum_amount() {
		// Check order amount
		switch (get_woocommerce_currency()) {
			case 'USD':
			case 'CAD':
			case 'EUR':
			case 'CHF':
			case 'AUD':
			case 'SGD':
				$minimum_amount = 50;
				break;
			case 'GBP':
				$minimum_amount = 30;
				break;
			case 'DKK':
				$minimum_amount = 250;
				break;
			case 'NOK':
			case 'SEK':
				$minimum_amount = 300;
				break;
			case 'JPY':
				$minimum_amount = 5000;
				break;
			case 'MXN':
				$minimum_amount = 1000;
				break;
			case 'HKD':
				$minimum_amount = 400;
				break;
			default:
				$minimum_amount = 50;
				break;
		}

		return $minimum_amount;
	}

	/**
	 * Gets all the saved setting options from a specific method.
	 * If specific setting is passed, only return that.
	 *
	 * @param string $method The payment method to get the settings from.
	 * @param string $setting The name of the setting to get.
	 */
	public static function get_settings($method = null, $setting = null) {
		$all_settings = null === $method ? get_option('woocommerce_yuansfer_settings', array()) : get_option('woocommerce_yuansfer_' . $method . '_settings', array());

		if (null === $setting) {
			return $all_settings;
		}

		return isset($all_settings[$setting]) ? $all_settings[$setting] : '';
	}

	/**
	 * Checks if Pre Orders is available.
	 *
	 * @return bool
	 */
	public static function is_pre_orders_exists() {
		return class_exists('WC_Pre_Orders_Order');
	}

    /**
     * Checks if WC version is less than passed in version.
     *
     * @since 4.1.11
     * @param string $version Version to check against.
     * @return bool
     */
    public static function is_wc_lt($version) {
        return version_compare(WC_VERSION, $version, '>=');
    }

	/**
	 * Gets the webhook URL for Yuansfer triggers. Used mainly for
	 * asyncronous redirect payment methods in which statuses are
	 * not immediately chargeable.
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		return add_query_arg('wc-api', 'wc_yuansfer', trailingslashit(get_home_url()));
	}

    /**
     * @param int $id
     * @return string
     */
    public static function get_redirect_url($id) {
        return add_query_arg('order_id', $id, self::get_webhook_url());
    }

	/**
	 * Gets the order by Yuansfer source ID.
	 *
	 * @param string $source_id
     * @return WC_Order|false
	 */
	public static function get_order_by_source_id($source_id) {
		global $wpdb;

		$order_id = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $source_id, '_yuansfer_source_id'));

		if (!empty($order_id)) {
			return wc_get_order($order_id);
		}

		return false;
	}

	/**
	 * Gets the order by Yuansfer charge ID.
	 *
	 * @param string $charge_id
	 */
	public static function get_order_by_charge_id($charge_id) {
		global $wpdb;

		$order_id = $wpdb->get_var($wpdb->prepare("SELECT DISTINCT ID FROM $wpdb->posts as posts LEFT JOIN $wpdb->postmeta as meta ON posts.ID = meta.post_id WHERE meta.meta_value = %s AND meta.meta_key = %s", $charge_id, '_transaction_id'));

		if (!empty($order_id)) {
			return wc_get_order($order_id);
		}

		return false;
	}

	/**
	 * Sanitize statement descriptor text.
	 *
	 * Yuansfer requires max of 22 characters and no
	 * special characters with ><"'.
	 *
	 * @param string $statement_descriptor
	 * @return string $statement_descriptor Sanitized statement descriptor
	 */
	public static function clean_statement_descriptor($statement_descriptor = '') {
		$disallowed_characters = array('<', '>', '"', "'");

		// Remove special characters.
		$statement_descriptor = str_replace($disallowed_characters, '', $statement_descriptor);

		$statement_descriptor = substr(trim($statement_descriptor), 0, 22);

		return $statement_descriptor;
	}

    /**
     * Check if the string is JSON or not
     *
     * @param string $string
     */
    public static function is_json($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Check if the string is HTML string
     *
     * @param string $string
     */
    function is_html($string){
        return $string != strip_tags($string) ? true : false;
    }
}
