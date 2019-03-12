<?php
if (!defined('ABSPATH')) {
	exit;
}

return apply_filters('wc_yuansfer_settings',
	array(
		'enabled' => array(
			'title'       => __('Enable/Disable', 'woocommerce-yuansfer'),
			'label'       => __('Enable China UnionPay via Yuansfer', 'woocommerce-yuansfer'),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title' => array(
			'title'       => __('Title', 'woocommerce-yuansfer'),
			'type'        => 'text',
			'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-yuansfer'),
			'default'     => __('China UnionPay (Yuansfer)', 'woocommerce-yuansfer'),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __('Description', 'woocommerce-yuansfer'),
			'type'        => 'text',
			'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-yuansfer'),
			'default'     => __('Pay with China UnionPay via Yuansfer.', 'woocommerce-yuansfer'),
			'desc_tip'    => true,
		),
		'merchant_no' => array(
            'title'       => __('Merchant No.', 'woocommerce-yuansfer'),
            'type'        => 'text',
            'description' => __('Get your merchant no. from your yuansfer account.', 'woocommerce-yuansfer'),
            'default'     => __('', 'woocommerce-yuansfer'),
            'desc_tip'    => true,
        ),
        'store_no' => array(
            'title'       => __('Store No.', 'woocommerce-yuansfer'),
            'type'        => 'text',
            'description' => __('Get your store no. from your yuansfer account.', 'woocommerce-yuansfer'),
            'default'     => __('', 'woocommerce-yuansfer'),
            'desc_tip'    => true,
        ),
		'testmode' => array(
			'title'       => __('Test mode', 'woocommerce-yuansfer'),
			'label'       => __('Enable Test Mode', 'woocommerce-yuansfer'),
			'type'        => 'checkbox',
			'description' => __('Place the payment in test mode using test API Token.', 'woocommerce-yuansfer'),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'test_api_token' => array(
			'title'       => __('Test API Token', 'woocommerce-yuansfer'),
			'type'        => 'password',
			'description' => __('Get your API tokens from your yuansfer account.', 'woocommerce-yuansfer'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'api_token' => array(
			'title'       => __('Live API Token', 'woocommerce-yuansfer'),
			'type'        => 'password',
			'description' => __('Get your API tokens from your yuansfer account.', 'woocommerce-yuansfer'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'statement_descriptor' => array(
			'title'       => __('Statement Descriptor', 'woocommerce-yuansfer'),
			'type'        => 'text',
			'description' => __('This may be up to 22 characters. The statement description must contain at least one letter, may not include ><"\' characters, and will appear on your customer\'s statement in capital letters.', 'woocommerce-yuansfer'),
			'default'     => '',
			'desc_tip'    => true,
		),
        'manager_no' => array(
            'title'       => __('Store Manager No', 'woocommerce-yuansfer'),
            'type'        => 'text',
            'description' => __('Required when store manager validation is set for refund', 'woocommerce-yuansfer'),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'manager_password' => array(
            'title'       => __('Store Manager Password', 'woocommerce-yuansfer'),
            'type'        => 'password',
            'description' => __('Store manager validation password.', 'woocommerce-yuansfer'),
            'default'     => '',
            'desc_tip'    => true,
        ),
		'logging' => array(
			'title'       => __('Logging', 'woocommerce-yuansfer'),
			'label'       => __('Log debug messages', 'woocommerce-yuansfer'),
			'type'        => 'checkbox',
			'description' => __('Save debug messages to the WooCommerce System Status log.', 'woocommerce-yuansfer'),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);
