<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Handles and process WC payment tokens API.
 * Seen in checkout page and my account->add payment method page.
 */
class WC_Yuansfer_Payment_Tokens {
	private static $_this;

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$_this = $this;

		add_filter('woocommerce_get_customer_payment_tokens', array($this, 'woocommerce_get_customer_payment_tokens'), 10, 3);
		add_action('woocommerce_payment_token_deleted', array($this, 'woocommerce_payment_token_deleted'), 10, 2);
		add_action('woocommerce_payment_token_set_default', array($this, 'woocommerce_payment_token_set_default'));
	}

	/**
	 * Public access to instance object.
	 */
	public static function get_instance() {
		return self::$_this;
	}

	/**
	 * Checks if customer has saved payment methods.
	 *
	 * @param int $customer_id
	 * @return bool
	 */
	public static function customer_has_saved_methods($customer_id) {
		$gateways = array('yuansfer');

		if (empty($customer_id)) {
			return false;
		}

		$has_token = false;

		foreach ($gateways as $gateway) {
			$tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, $gateway);

			if (!empty($tokens)) {
				$has_token = true;
				break;
			}
		}

		return $has_token;
	}

	/**
	 * Gets saved tokens from API if they don't already exist in WooCommerce.
	 *
	 * @param array $tokens
	 * @return array
	 */
	public function woocommerce_get_customer_payment_tokens($tokens = array(), $customer_id, $gateway_id) {
		if (is_user_logged_in() && class_exists('WC_Payment_Token_CC')) {
			$stored_tokens = array();

			foreach ($tokens as $token) {
				$stored_tokens[] = $token->get_token();
			}

			if ('yuansfer' === $gateway_id) {
				$yuansfer_customer = new WC_Yuansfer_Customer($customer_id);
				$yuansfer_sources  = $yuansfer_customer->get_sources();

				foreach ($yuansfer_sources as $source) {
					if (isset($source->type) && 'card' === $source->type) {
						if (!in_array($source->id, $stored_tokens)) {
							$token = new WC_Payment_Token_CC();
							$token->set_token($source->id);
							$token->set_gateway_id('yuansfer');

							if ('source' === $source->object && 'card' === $source->type) {
								$token->set_card_type(strtolower($source->card->brand));
								$token->set_last4($source->card->last4);
								$token->set_expiry_month($source->card->exp_month);
								$token->set_expiry_year($source->card->exp_year);
							}

							$token->set_user_id($customer_id);
							$token->save();
							$tokens[$token->get_id()] = $token;
						}
					} else {
						if (!in_array($source->id, $stored_tokens) && 'card' === $source->object) {
							$token = new WC_Payment_Token_CC();
							$token->set_token($source->id);
							$token->set_gateway_id('yuansfer');
							$token->set_card_type(strtolower($source->brand));
							$token->set_last4($source->last4);
							$token->set_expiry_month($source->exp_month);
							$token->set_expiry_year($source->exp_year);
							$token->set_user_id($customer_id);
							$token->save();
							$tokens[$token->get_id()] = $token;
						}
					}
				}
			}
		}

		return $tokens;
	}

	/**
	 * Delete token from Yuansfer.
	 */
	public function woocommerce_payment_token_deleted($token_id, $token) {
		if ('yuansfer' === $token->get_gateway_id()) {
			$yuansfer_customer = new WC_Yuansfer_Customer(get_current_user_id());
			$yuansfer_customer->delete_source($token->get_token());
		}
	}

	/**
	 * Set as default in Yuansfer.
	 */
	public function woocommerce_payment_token_set_default($token_id) {
		$token = WC_Payment_Tokens::get($token_id);

		if ('yuansfer' === $token->get_gateway_id()) {
			$yuansfer_customer = new WC_Yuansfer_Customer(get_current_user_id());
			$yuansfer_customer->set_default_source($token->get_token());
		}
	}
}

new WC_Yuansfer_Payment_Tokens();
