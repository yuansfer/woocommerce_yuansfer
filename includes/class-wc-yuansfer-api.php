<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Yuansfer_API class.
 *
 * Communicates with Yuansfer API.
 */
class WC_Yuansfer_API {

    const KEY = 'verifySign';
    const VERSION = 'v2';

    /**
	 * Yuansfer API URL
	 */
    const PRODUCTION_URL = 'https://mapi.yuansfer.com/appTransaction',
        TEST_URL = 'https://mapi.yuansfer.yunkeguan.com/appTransaction';

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $api_token = '';

    /**
     * @var string
     */
    private static $url = '';

	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_api_token( $key ) {
		self::$api_token = $key;
	}

	/**
	 * Get secret key.
	 * @return string
	 */
	public static function get_api_token() {
		if ( ! self::$api_token ) {
			$options = get_option( 'woocommerce_yuansfer_settings' );

			if ( isset( $options['testmode'], $options['api_token'], $options['test_api_token'] ) ) {
				self::set_api_token( 'yes' === $options['testmode'] ? $options['test_api_token'] : $options['api_token'] );
			}
		}

		return self::$api_token;
	}

    /**
     * @param string $url
     */
	public static function set_url( $url ) {
	    self::$url = $url;
    }

    /**
     * @return string
     */
	public static function get_url() {
        if ( ! self::$url ) {
            $options = get_option('woocommerce_yuansfer_settings');

            if (isset($options['testmode'])) {
                self::set_url('yes' === $options['testmode'] ? self::TEST_URL : self::PRODUCTION_URL);
            }
        }

        return self::$url;
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
	public static function request( $request, $api, $method = 'POST' ) {
		WC_Yuansfer_Logger::log( "{$api} request: " . print_r( $request, true ) );

		$request = self::append_sign($request);

		$response = wp_safe_remote_post(
			self::get_url() . '/' . self::VERSION . '/' . $api,
			array(
				'method'  => $method,
				'body'    => apply_filters( 'woocommerce_yuansfer_request_body', $request, $api ),
				'timeout' => 70,
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			WC_Yuansfer_Logger::log( 'Error Response: ' . print_r( $response, true ) . PHP_EOL . PHP_EOL . 'Failed request: ' . print_r( array(
				'api'             => $api,
				'request'         => $request,
			), true ) );
			throw new WC_Yuansfer_Exception( print_r( $response, true ), __( 'There was a problem connecting to the Yuansfer API endpoint.', 'woocommerce-yuansfer' ) );
		}

		if ($api === 'securepay') {
		    return $response['body'];
        }

		return json_decode( $response['body'] );
	}

    /**
     * @param array $params
     *
     * @return string
     */
    protected static function generate_sign(&$params)
    {
        unset($params[static::KEY]);

        \ksort($params, SORT_STRING);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }

        $token = self::get_api_token();

        return \md5($str . \md5($token));
    }


    /**
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

        return $verifySign === static::generate_sign($params);
    }

	/**
	 * Retrieve API endpoint.
     *
	 * @param string $api
     * @return array|WP_Error
	 */
	public static function retrieve( $api ) {
		WC_Yuansfer_Logger::log( $api );

		$response = wp_safe_remote_get(
			self::get_url() . $api,
			array(
				'method'  => 'GET',
				'timeout' => 70,
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			WC_Yuansfer_Logger::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'yuansfer_error', __( 'There was a problem connecting to the Yuansfer API endpoint.', 'woocommerce-yuansfer' ) );
		}

		return json_decode( $response['body'] );
	}
}
