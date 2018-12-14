<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Yuansfer_Webhook_Handler.
 *
 * Handles webhooks from Yuansfer.
 */
class WC_Yuansfer_Webhook_Handler extends WC_Yuansfer_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_wc_yuansfer', array( $this, 'check_for_webhook' ) );
	}

	/**
	 * Check incoming requests for Yuansfer Webhook data and process them.
     * @throws Exception
	 */
	public function check_for_webhook() {
		if ( ! isset( $_GET['wc-api'] )
			|| ( 'wc_yuansfer' !== $_GET['wc-api'] )
		) {
			return;
		}

		if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $request_body = file_get_contents('php://input');
            $request_headers = array_change_key_case($this->get_request_headers(), CASE_UPPER);

            // 推荐用法
            parse_str($request_body, $post);

            // Validate it to make sure it is legit.
            if ($this->is_valid_request($request_headers, $post)) {
                $this->process_webhook($post);
                status_header(200);
                exit;
            }

            WC_Yuansfer_Logger::log('Incoming webhook failed validation: ' . print_r($request_body, true));
            status_header(400);
            exit;
        }

        if ('GET' === $_SERVER['REQUEST_METHOD']) {
		    $order_id = isset($_GET['order_id']) ? $_GET['order_id'] : '';

		    if ( ! $order_id ) {
                WC_Yuansfer_Logger::log( 'Order ID is empty' );
                exit;
            }

            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                WC_Yuansfer_Logger::log( 'Could not find order: ' . $order_id );
                exit;
            }

            $response = WC_Yuansfer_Helper::is_pre_30() ? get_post_meta( $order_id, '_yuansfer_response', true ) : $order->get_meta( '_yuansfer_response', true );

            if ( ! $response ) {
				WC_Yuansfer_Logger::log( 'Order response not found: ' . $order_id );
                exit;
            }

//            $order->update_status( 'on-hold', __( 'Awaiting payment', 'woocommerce-yuansfer' ) );

            echo $response;
            exit;
        }
	}

	private function get_order($reference) {
		list($order_id) = explode(':', $reference);

		$order = wc_get_order( $order_id );

		if ($order) {
			if ( WC_Yuansfer_Helper::is_pre_30() ) {
				delete_post_meta( $order_id, '_yuansfer_response' );
				update_post_meta( $order_id, '_yuansfer_reference', $reference );
			} else {
				$order->delete_meta_data( '_yuansfer_response' );
				$order->update_meta_data( '_yuansfer_reference', $reference );
				$order->save();
			}
		}

		return $order;
	}

	/**
	 * Verify the incoming webhook notification to make sure it is legit.
	 *
	 * @param string $request_headers The request headers from Yuansfer.
	 * @param array  $post The post params from Yuansfer.
	 * @return bool
	 */
	public function is_valid_request( $request_headers = null, $post = null ) {
		if ( null === $request_headers || null === $post ) {
			return false;
		}

		if (!isset($post['status'], $post['reference'])) {
		    return false;
        }

        if (!WC_Yuansfer_API::verify_sign($post)) {
		    return false;
        }

		return true;
	}

	/**
	 * Gets the incoming request headers. Some servers are not using
	 * Apache and "getallheaders()" will not work so we may need to
	 * build our own headers.
	 */
	public function get_request_headers() {
		if ( ! function_exists( 'getallheaders' ) ) {
			$headers = array();

			foreach ( $_SERVER as $name => $value ) {
				if ( strpos($name, 'HTTP_') === 0 ) {
					$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
				}
			}

			return $headers;
		}

		return getallheaders();
	}

	/**
	 * Process webhook succeeded. This is used for payment methods
	 * that takes time to clear which is asynchronous.
	 *
	 * @param array $post
     * @throws Exception
	 */
	public function process_webhook_success( $post ) {
		$order = $this->get_order( $post['reference'] );

		if ( ! $order ) {
			WC_Yuansfer_Logger::log( 'Could not find order: ' . $post['reference'] );
			return;
		}

        $order_id = WC_Yuansfer_Helper::is_pre_30() ? $order->id : $order->get_id();
		$status = $order->get_status();

		if ( 'on-hold' !== $status && 'pending' !== $status ) {
            return;
        }

		// Store other data such as fees
		WC_Yuansfer_Helper::is_pre_30() ? update_post_meta( $order_id, '_transaction_id', $post['yuansferId'] ) : $order->set_transaction_id( $post['yuansferId'] );

		$order->payment_complete( $post['yuansferId'] );

		/* translators: transaction id */
		$order->add_order_note( sprintf( __( 'Yuansfer charge complete (Charge ID: %s)', 'woocommerce-yuansfer' ), $post['yuansferId'] ) );

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		echo 'success';
	}

	/**
	 * Process webhook charge failed.
	 *
	 * @param array $post
	 */
	public function process_webhook_failed( $post ) {
        $order = $this->get_order( $post['reference'] );

		if ( ! $order ) {
			WC_Yuansfer_Logger::log( 'Could not find order: ' . $post['reference'] );
			return;
		}

		// If order status is already in failed status don't continue.
		if ( 'failed' === $order->get_status() ) {
			return;
		}

		$order->update_status( 'failed', __( 'This payment failed to clear.', 'woocommerce-yuansfer' ) );

		do_action( 'wc_gateway_yuansfer_process_webhook_payment_error', $order, $post );
	}

	/**
	 * Process webhook source canceled. This is used for payment methods
	 * that redirects and awaits payments from customer.
	 *
	 * @param array $post
	 */
	public function process_webhook_canceled( $post ) {
        $order = $this->get_order( $post['reference'] );

		if ( ! $order ) {
            WC_Yuansfer_Logger::log( 'Could not find order: ' . $post['reference'] );
			return;
		}

        $status = $order->get_status();

		if ( 'on-hold' !== $status || 'cancelled' !== $status ) {
			return;
		}

		$order->update_status( 'cancelled', __( 'This payment has cancelled.', 'woocommerce-yuansfer' ) );

		do_action( 'wc_gateway_yuansfer_process_webhook_payment_error', $order, $post );
	}

	/**
	 * Processes the incoming webhook.
	 *
	 * @param array $post
     * @throws Exception
	 */
	public function process_webhook( $post ) {

        switch ( $post['status'] ) {
            case 'success':
                $this->process_webhook_success($post);
                break;

            case 'fail':
                $this->process_webhook_failed($post);
                break;

            case 'closed':
            case 'reversed':
                $this->process_webhook_canceled($post);
                break;
        }
	}
}

new WC_Yuansfer_Webhook_Handler();
