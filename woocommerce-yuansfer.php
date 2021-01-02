<?php
/*
 * Plugin Name: WooCommerce Yuansfer
 * Plugin URI: https://wordpress.org/plugins/woo-yuansfer/
 * Description: Provides a Yuansfer Payment Gateway
 * Author: Yuansfer
 * Author URI: https://www.yuansfer.com/
 * Version: 3.0.3
 * WC requires at least: 3.0
 * WC tested up to: 4.3
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if the WooCommerce is installed and activate
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins',get_option('active_plugins')))) {
    add_action('plugins_loaded', 'woocommerce_yuansfer_init');
} else {
    add_action('admin_notices', 'woocommerce_yuansfer_missing_wc_notice');
    return;
}

/**
 * Install Yuansfer
 *
 * @return void
 */
function woocommerce_yuansfer_init()
{
    load_plugin_textdomain('woocommerce-yuansfer', false, plugin_basename(dirname(__FILE__)) . '/languages');

    /**
     * Required minimums and constants
     */
    define('WC_YUANSFER_VERSION', '3.0.3');
    define('WC_YUANSFER_MIN_PHP_VER', '5.6.0');
    define('WC_YUANSFER_MIN_WC_VER', '3.0');
    define('WC_YUANSFER_MAIN_FILE', __FILE__);
    define('WC_YUANSFER_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
    define('WC_YUANSFER_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

    WC_Yuansfer::get_instance();
}


/**
 * WooCommerce fallback notice.
 *
 * @return string
 */
function woocommerce_yuansfer_missing_wc_notice()
{
    echo '<div class="error"><p>Yuansfer requires <strong>WooCommerce</strong> to be installed and active. ' .
        sprintf(esc_html__('You can download %s here.', 'woocommerce-yuansfer'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') .
        '</p></div>';
}


/**
 * WC_Yuansfer Class.
 */
class WC_Yuansfer
{
    /**
     * @var WC_Yuansfer Singleton The reference the *Singleton* instance of this class
     */
    private static $instance;

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return WC_Yuansfer Singleton The *Singleton* instance.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    private function __construct()
    {
        add_action('admin_init', array($this, 'install'));
        $this->init();
    }

    /**
     * Init the plugin after plugins_loaded so environment variables are set.
     */
    public function init()
    {
        if (is_admin()) {
            require_once __DIR__ . '/includes/admin/class-wc-yuansfer-privacy.php';
        }

        require_once __DIR__ . '/includes/class-wc-yuansfer-exception.php';
        require_once __DIR__ . '/includes/class-wc-yuansfer-logger.php';
        require_once __DIR__ . '/includes/class-wc-yuansfer-helper.php';
        include_once __DIR__ . '/includes/class-wc-yuansfer-api.php';
        require_once __DIR__ . '/includes/abstracts/abstract-wc-yuansfer-payment-gateway.php';
        require_once __DIR__ . '/includes/class-wc-yuansfer-webhook-handler.php';
        require_once __DIR__ . '/includes/compat/class-wc-yuansfer-pre-orders-compat.php';
        require_once __DIR__ . '/includes/class-wc-gateway-yuansfer.php';
        require_once __DIR__ . '/includes/payment-methods/class-wc-gateway-yuansfer-alipay.php';
        require_once __DIR__ . '/includes/payment-methods/class-wc-gateway-yuansfer-wechatpay.php';
        require_once __DIR__ . '/includes/payment-methods/class-wc-gateway-yuansfer-creditcard.php';
        require_once __DIR__ . '/includes/payment-methods/class-wc-gateway-yuansfer-paypal.php';
        require_once __DIR__ . '/includes/payment-methods/class-wc-gateway-yuansfer-venmo.php';
        require_once __DIR__ . '/includes/class-wc-yuansfer-order-handler.php';
        require_once __DIR__ . '/includes/class-wc-yuansfer-payment-tokens.php';
        require_once __DIR__ . '/includes/class-wc-yuansfer-customer.php';
        require_once __DIR__ . '/includes/class-wc-yuansfer-mobile-detect.php';

        if (is_admin()) {
            require_once __DIR__ . '/includes/admin/class-wc-yuansfer-admin-notices.php';
        }

        add_filter('woocommerce_payment_gateways', array($this, 'add_gateways'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));

        if (version_compare(WC_VERSION, '3.4', '<')) {
            add_filter('woocommerce_get_sections_checkout', array($this, 'filter_gateway_order_admin'));
        }
    }

    /**
     * Handles upgrade routines.
     */
    public function install()
    {
        if (!is_plugin_active(plugin_basename(__FILE__))) {
            return;
        }

        if (!defined('IFRAME_REQUEST') && (WC_YUANSFER_VERSION !== get_option('wc_yuansfer_version'))) {
            do_action('woocommerce_yuansfer_updated');

            if (!defined('WC_YUANSFER_INSTALLING')) {
                define('WC_YUANSFER_INSTALLING', true);
            }

            $this->update_plugin_version();
        }
    }

    /**
     * Updates the plugin version in db
     */
    public function update_plugin_version()
    {
        delete_option('wc_yuansfer_version');
        update_option('wc_yuansfer_version', WC_YUANSFER_VERSION);
    }

    /**
     * Adds plugin action links.
     */
    public function plugin_action_links($links)
    {
        $plugin_links = array(
            '<a href="admin.php?page=wc-settings&tab=checkout&section=yuansfer">' .
            esc_html__('Settings', 'woocommerce-yuansfer') . '</a>',
            '<a href="https://faq.yuansfer.com/" target="_blank">' .
            esc_html__('Support', 'woocommerce-yuansfer') . '</a>',
        );

        return array_merge($plugin_links, $links);
    }

    /**
     * Add the gateways to WooCommerce.
     */
    public function add_gateways($methods)
    {
        $methods[] = 'WC_Gateway_Yuansfer';
        $methods[] = 'WC_Gateway_Yuansfer_Alipay';
        $methods[] = 'WC_Gateway_Yuansfer_Wechatpay';
        $methods[] = 'WC_Gateway_Yuansfer_Creditcard';
        $methods[] = 'WC_Gateway_Yuansfer_Paypal';
        $methods[] = 'WC_Gateway_Yuansfer_Venmo';
        return $methods;
    }

    /**
     * Modifies the order of the gateways displayed in admin.
     */
    public function filter_gateway_order_admin($sections)
    {
        unset(
            $sections['yuansfer'],
            $sections['yuansfer_alipay'],
            $sections['yuansfer_wechatpay'],
            $sections['yuansfer_creditcard'],
            $sections['yuansfer_paypal'],
            $sections['yuansfer_venmo']
        );

        $sections['yuansfer'] = __('Yuansfer UnionPay', 'woocommerce-yuansfer');
        $sections['yuansfer_alipay'] = __('Yuansfer Alipay', 'woocommerce-yuansfer');
        $sections['yuansfer_wechatpay'] = __('Yuansfer WeChat Pay', 'woocommerce-yuansfer');
        $sections['yuansfer_creditcard'] = __('Yuansfer Credit Card', 'woocommerce-yuansfer');
        $sections['yuansfer_paypal'] = __('Yuansfer PayPal', 'woocommerce-yuansfer');
        $sections['yuansfer_venmo'] = __('Yuansfer Venmo', 'woocommerce-yuansfer');

        return $sections;
    }
}
