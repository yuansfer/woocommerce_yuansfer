<?php
if (!defined('ABSPATH')) {
	exit;
}

// if uninstall not called from WordPress exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

/*
 * Only remove ALL product and page data if WC_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if (defined('WC_REMOVE_ALL_DATA') && true === WC_REMOVE_ALL_DATA) {
	// Delete options.
	delete_option('woocommerce_yuansfer_settings');
	delete_option('wc_yuansfer_show_request_api_notice');
	delete_option('wc_yuansfer_show_ssl_notice');
	delete_option('wc_yuansfer_show_keys_notice');
	delete_option('wc_yuansfer_show_alipay_notice');
    delete_option('wc_yuansfer_show_wechatpay_notice');
	delete_option('wc_yuansfer_version');
	delete_option('woocommerce_yuansfer_alipay_settings');
    delete_option('woocommerce_yuansfer_wechatpay_settings');
}
