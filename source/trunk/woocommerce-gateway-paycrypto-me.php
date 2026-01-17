<?php
/**
 * Plugin Name: PayCrypto.Me for WooCommerce
 * Plugin URI: https://github.com/paycrypto-me/woocommerce-gateway-paycrypto-me/
 * Description: PayCrypto.Me Payments for WooCommerce offers a complete solution that allows your customers to pay using many cryptocurrencies in your store.
 * Version: 0.1.0
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Contributors: lucasrosa95
 * Donate link: https://gravatar.com/lucasrosa95
 * Author: PayCrypto.Me
 * Author URI: https://paycrypto.me/
 * Text Domain: woocommerce-gateway-paycrypto-me
 * Domain Path: /languages/
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace PayCryptoMe\WooCommerce;

defined('ABSPATH') || exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

register_activation_hook(__FILE__, [PayCryptoMeBitcoinGatewayActivate::class, 'activate']);

if (!class_exists(__NAMESPACE__ . '\\WC_PayCryptoMe')) {
    class WC_PayCryptoMe
    {
        public const string VERSION = '0.1.0';
        protected static $instance = null;

        protected function __construct()
        {
            $this->includes();

            add_action('init', [$this, 'load_textdomain']);
            add_filter('woocommerce_payment_gateways', [__CLASS__, 'add_gateway']);
            add_action('before_woocommerce_init', [$this, 'declare_wc_compatibility']);
            add_action('woocommerce_blocks_loaded', [$this, 'load_blocks_support']);
        }

        public static function instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function plugin_url()
        {
            return untrailingslashit(plugins_url('/', __FILE__));
        }

        public static function plugin_abspath()
        {
            return trailingslashit(plugin_dir_path(__FILE__));
        }

        public static function add_gateway($gateways)
        {
            $options = get_option('woocommerce_paycrypto_me_settings', []);

            $hide_for_non_admin_users =
                isset($options['hide_for_non_admin_users']) ? $options['hide_for_non_admin_users'] : 'no';

            if (
                ('yes' === $hide_for_non_admin_users && current_user_can('manage_options')) ||
                'no' === $hide_for_non_admin_users
            ) {
                $gateways[] = __NAMESPACE__ . '\WC_Gateway_PayCryptoMe';
            }

            return $gateways;
        }

        public function load_textdomain()
        {
            load_plugin_textdomain('woocommerce-gateway-paycrypto-me', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        protected function includes()
        {
            if (class_exists('WC_Payment_Gateway')) {
                include_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-paycrypto-me.php';
            }
        }

        public function declare_wc_compatibility()
        {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('woocommerce_blocks', __FILE__, true);
            }
        }
        public function load_blocks_support()
        {
            if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                include_once plugin_dir_path(__FILE__) . 'includes/blocks/class-wc-gateway-paycrypto-me-blocks.php';
            }
        }
        public static function log($message, $level = 'info')
        {
            $logger = \wc_get_logger();
            $logger->log($level, $message, ['source' => 'paycrypto_me']);
        }
        public function __clone()
        {
            _doing_it_wrong(__FUNCTION__, 'Cloning is forbidden.', '0.1.0');
        }
        public function __wakeup()
        {
            _doing_it_wrong(__FUNCTION__, 'Unserializing is forbidden.', '0.1.0');
        }
    }
}

function wc_paycrypto_me_initialize()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            if (!headers_sent()) {
                echo '<div class="error"><p>PayCrypto.Me for WooCommerce requires WooCommerce to be installed and active.</p></div>';
            }
        });
        return;
    }

    \PayCryptoMe\WooCommerce\WC_PayCryptoMe::instance();
}

add_action('plugins_loaded', __NAMESPACE__ . '\\wc_paycrypto_me_initialize', 10);

function paycrypto_me_before_payment($order_id, $data)
{
    do_action('paycrypto_me_before_payment', $order_id, $data);
}

