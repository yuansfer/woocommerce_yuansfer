<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_yuansfer_lakalapay_settings',
	array(
		'geo_target' => array(
			'description' => __( 'Relevant Payer Geography: China', 'woocommerce-yuansfer' ),
			'type'        => 'title',
		),
		'guide' => array(
			'description' => __( '<a href="https://faq.yuansfer.com/" target="_blank">FAQ</a>', 'woocommerce-yuansfer' ),
			'type'        => 'title',
		),
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-yuansfer' ),
			'label'       => __( 'Enable Lakala Pay via Yuansfer', 'woocommerce-yuansfer' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-yuansfer' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-yuansfer' ),
			'default'     => __( 'Lakala Pay (Yuansfer)', 'woocommerce-yuansfer' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-yuansfer' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-yuansfer' ),
			'default'     => __( 'Pay with Lakala Pay via Yuansfer.', 'woocommerce-yuansfer' ),
			'desc_tip'    => true,
		),
	)
);
