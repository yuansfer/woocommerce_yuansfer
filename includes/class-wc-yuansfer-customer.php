<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Yuansfer_Customer class.
 *
 * Represents a Yuansfer Customer.
 */
class WC_Yuansfer_Customer {

    /**
     * Yuansfer customer ID
     * @var string
     */
    private $id = '';

    /**
     * WP User ID
     * @var integer
     */
    private $user_id = 0;

    /**
     * Data from API
     * @var array
     */
    private $customer_data = array();

    public $merchant_no;
    public $store_no;

    /**
     * Constructor
     * @param int $user_id The WP user ID
     */
    public function __construct( $user_id = 0 ) {
        if ( $user_id ) {
            $this->set_user_id( $user_id );
            $this->set_id( $this->get_id_from_meta( $user_id ) );
        }

        $main_settings     = get_option('woocommerce_yuansfer_settings');
        $this->merchant_no = !empty($main_settings['merchant_no']) ? $main_settings['merchant_no'] : '';
        $this->store_no    = !empty($main_settings['store_no']) ? $main_settings['store_no'] : '';
    }

    /**
     * Get Yuansfer customer ID.
     * @return string
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Set Yuansfer customer ID.
     * @param [type] $id [description]
     */
    public function set_id( $id ) {
        // Backwards compat for customer ID stored in array format. (Pre 3.0)
        if ( is_array( $id ) && isset( $id['customer_id'] ) ) {
            $id = $id['customer_id'];

            $this->update_id_in_meta( $id );
        }

        $this->id = wc_clean( $id );
    }

    /**
     * User ID in WordPress.
     * @return int
     */
    public function get_user_id() {
        return absint( $this->user_id );
    }

    /**
     * Set User ID used by WordPress.
     * @param int $user_id
     */
    public function set_user_id( $user_id ) {
        $this->user_id = absint( $user_id );
    }

    /**
     * Get user object.
     * @return WP_User
     */
    protected function get_user() {
        return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
    }

    /**
     * Store data from the Yuansfer API about this customer
     */
    public function set_customer_data( $data ) {
        $this->customer_data = $data;
    }

    /**
     * Generates the customer request, used for both creating and updating customers.
     *
     * @param  array $args Additional arguments (optional).
     * @return array
     */
    protected function generate_customer_request( $args = array() ) {
        $args += array(
            'merchantNo'    => $this->merchant_no,
            'storeNo'       => $this->store_no,
        );

        return $args;
    }

    /**
     * Create a customer via API.
     * @param array $args
     * @return WP_Error|int
     */
    public function create_customer($args) {
        $args['groupCode'] = 'HPP';
        $args     = $this->generate_customer_request( $args );
        $response = WC_Yuansfer_API::request( $args, 'creditpay:customer/add' );

        if ( empty( $response->ret_code ) || $response->ret_code !== '000100' ) {
            throw new WC_Yuansfer_Exception( print_r( $response, true ), $response->error->message );
        }

        $customer = $response->customerInfo;

        $this->set_id( $customer->customerNo );
        $this->clear_cache();
        $this->set_customer_data( $customer );

        if ( $this->get_user_id() ) {
            $this->update_id_in_meta( $customer->customerNo );
        }

        do_action( 'woocommerce_yuansfer_add_customer', $args, $response );

        return $customer->customerNo;
    }

    /**
     * Updates the Yuansfer customer through the API.
     *
     * @param array $args
     * @param bool  $is_retry Whether the current call is a retry (optional, defaults to false). If true, then an exception will be thrown instead of further retries on error.
     *
     * @return string Customer ID
     *
     * @throws WC_Yuansfer_Exception
     */
    public function update_customer( $args, $is_retry = false ) {
        if ( empty( $this->get_id() ) ) {
            throw new WC_Yuansfer_Exception( 'id_required_to_update_user', __( 'Attempting to update a Yuansfer customer without a customer ID.', 'woocommerce-gateway-yuansfer' ) );
        }

        $args['customerNo'] = $this->get_id();
        $args     = $this->generate_customer_request($args);
        $response = WC_Yuansfer_API::request( $args, 'creditpay:customer/edit' );

        if ( empty( $response->ret_code ) || $response->ret_code !== '000100' ) {
            if ( ! $is_retry && $this->is_no_such_customer_error( $response->ret_msg ) ) {
                // This can happen when switching the main Yuansfer account or importing users from another site.
                // If not already retrying, recreate the customer and then try updating it again.
                $this->recreate_customer();
                return $this->update_customer( $args, true );
            }

            throw new WC_Yuansfer_Exception( $response->ret_msg );
        }

        $this->clear_cache();
        $this->set_customer_data( $response->customerInfo );

        do_action( 'woocommerce_yuansfer_update_customer', $args, $response );

        return $this->get_id();
    }

    /**
     * Checks to see if error is of invalid request
     * error and it is no such customer.
     *
     * @since 4.1.2
     * @param array $error
     */
    public function is_no_such_customer_error( $error ) {
        return (
            $error &&
            preg_match( '/No such customer/i', $error )
        );
    }

    /**
     * Deletes caches for this users cards.
     */
    public function clear_cache() {
        delete_transient( 'yuansfer_customer_' . $this->get_id() );
        $this->customer_data = array();
    }

    /**
     * Retrieves the Yuansfer Customer ID from the user meta.
     *
     * @param  int $user_id The ID of the WordPress user.
     * @return string|bool  Either the Yuansfer ID or false.
     */
    public function get_id_from_meta( $user_id ) {
        return get_user_option( '_yuansfer_customer_id', $user_id );
    }

    /**
     * Updates the current user with the right Yuansfer ID in the meta table.
     *
     * @param string $id The Yuansfer customer ID.
     */
    public function update_id_in_meta( $id ) {
        update_user_option( $this->get_user_id(), '_yuansfer_customer_id', $id, false );
    }

    /**
     * Deletes the user ID from the meta table with the right key.
     */
    public function delete_id_from_meta() {
        delete_user_option( $this->get_user_id(), '_yuansfer_customer_id', false );
    }

    /**
     * Recreates the customer for this user.
     *
     * @return string ID of the new Customer object.
     */
    private function recreate_customer() {
        $this->delete_id_from_meta();
        return $this->create_customer();
    }
}