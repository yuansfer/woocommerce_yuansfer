<?php
if (!defined('ABSPATH')) {
	exit;
}

/**
 * WC_Yuansfer_API class.
 *
 * Communicates with Yuansfer API.
 */
class WC_Yuansfer_API {

    const KEY = 'verifySign';

    const SECURE_PAY = '/online/v3/secure-pay';
    const REFUND = '/app-data-search/v3/refund';
    const CUSTOMER_ADD = '/creditpay/v2/customer/add';
    const CUSTOMER_EDIT = '/creditpay/v2/customer/edit';

    /**
	 * Yuansfer API URL
	 */
    const PRODUCTION_URL = 'https://mapi.yuansfer.com';
    const TEST_URL = 'https://mapi.yuansfer.yunkeguan.com';

	/**
	 * Secret API Token.
	 * @var string
	 */
	private static $api_token = '';

    /**
     * @var string
     */
    private static $url = '';

	/**
	 * Set secret API Token.
	 * @param string $token
	 */
	public static function set_api_token($token) {
		self::$api_token = $token;
	}

	/**
	 * Get secret API Token.
	 * @return string
	 */
	public static function get_api_token() {
		if (!self::$api_token) {
			$options = get_option('woocommerce_yuansfer_settings');

			if (isset($options['testmode'], $options['api_token'], $options['test_api_token'])) {
				self::set_api_token('yes' === $options['testmode'] ? $options['test_api_token'] : $options['api_token']);
			}
		}

		return self::$api_token;
	}

    /**
     * Set API Url
     * @param string $url
     */
	public static function set_url($url) {
	    self::$url = $url;
    }

    /**
     * Get API Url
     * @return string
     */
	public static function get_url() {
        if (!self::$url) {
            $options = get_option('woocommerce_yuansfer_settings');

            if (isset($options['testmode'])) {
                self::set_url('yes' === $options['testmode'] ? self::TEST_URL : self::PRODUCTION_URL);
            }
        }

        return self::$url;
    }

    /**
     * Generate verifySign
     * @param array $params
     *
     * @return string
     */
    protected static function generate_sign($params)
    {
        \ksort($params, SORT_STRING);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }

        return \md5($str . \md5(self::get_api_token()));
    }

    /**
     * Append verifySign
     * @param array $params
     *
     * @return array
     */
    public static function append_sign($params)
    {
        $params[static::KEY] = static::generate_sign($params);

        return $params;
    }

    /**
     * Check if the verifySign is correct
     * @param array $params
     *
     * @return bool
     */
    public static function verify_sign($params)
    {
        if (!isset($params[static::KEY])) {
            return false;
        }

        $verifySign = $params[static::KEY];
        unset($params[static::KEY]);

        return $verifySign === static::generate_sign($params);
    }

	/**
	 * Send the request to Yuansfer's API
     *
     * @param array  $request
     * @param string $api
     * @param string $method
     *
     * @return array
     * @throws WC_Yuansfer_Exception
     */
	public static function request($request, $api, $method = 'POST') {
		WC_Yuansfer_Logger::log("{$api} request: " . print_r($request, true));

		$request = self::append_sign($request);

		$response = wp_safe_remote_post(
			self::get_url() . $api,
			array(
				'method'  => $method,
				'body'    => apply_filters( 'woocommerce_yuansfer_request_body', $request, $api ),
				'timeout' => 70,
			)
		);

		if (is_wp_error($response) || empty($response['body'])) {
			WC_Yuansfer_Logger::log('Error Response: ' . print_r($response, true) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r(array(
				'api'             => $api,
				'request'         => $request,
			), true ));
			throw new WC_Yuansfer_Exception(print_r($response, true), __('There was a problem connecting to the Yuansfer API endpoint.', 'woocommerce-yuansfer'));
		}

		return json_decode($response['body']);
	}

    /**
     * Retrieve API endpoint.
     *
     * @param string $api
     * @return array|WP_Error
     */
    public static function retrieve($api) {
        WC_Yuansfer_Logger::log($api);

        $response = wp_safe_remote_get(
            self::get_url() . $api,
            array(
                'method'  => 'GET',
                'timeout' => 70,
            )
        );

        if (is_wp_error($response) || empty($response['body'])) {
            WC_Yuansfer_Logger::log('Error Response: ' . print_r( $response, true));
            return new WP_Error('yuansfer_error', __('There was a problem connecting to the Yuansfer API endpoint.', 'woocommerce-yuansfer'));
        }

        return json_decode($response['body']);
    }
}
