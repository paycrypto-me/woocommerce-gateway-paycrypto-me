<?php
/**
 * PayCrypto.Me Gateway for WooCommerce
 *
 * @package     WooCommerce\PayCryptoMe
 * @class       WC_Gateway_PayCryptoMe
 * @extends     WC_Payment_Gateway
 * @author      PayCrypto.Me
 * @copyright   2025 PayCrypto.Me
 * @license     GNU General Public License v3.0
 */

namespace PayCryptoMe\WooCommerce;

\defined('ABSPATH') || exit;

use BitWasp\Bitcoin\Network\NetworkFactory;

class WC_Gateway_PayCryptoMe extends \WC_Payment_Gateway
{
    protected $hide_for_non_admin_users;
    protected $configured_networks;
    protected $debug_log;
    protected $payment_timeout_hours;
    protected $payment_number_confirmations;
    private BitcoinAddressService $bitcoin_address_service;
    private QrCodeService $qr_code_service;
    private PayCryptoMeDBStatementsService $db_statements_service;
    private $support_btc_address = 'bc1qgvc07956sxuudk3jku6n03q5vc9tkrvkcar7uw';
    private $support_btc_payment_address = 'PM8TJdrkRoSqkCWmJwUMojQCG1rEXsuCTQ4GG7Gub7SSMYxaBx7pngJjhV8GUeXbaJujy8oq5ybpazVpNdotFftDX7f7UceYodNGmffUUiS5NZFu4wq4';

    public function __construct()
    {
        $this->id = 'paycrypto_me';

        $this->has_fields = true;

        $this->supports = ['products', 'pre-orders'];

        $this->icon = WC_PayCryptoMe::plugin_url() . '/assets/paycrypto-me-icon.png';
        $this->method_title = 'PayCrypto.Me (Bitcoin)';
        $this->method_description = _x('PayCrypto.Me (Bitcoin) introduces a complete solution to receive your payments directly to your Bitcoin wallet (Non-custodial).', 'Gateway description', 'paycrypto-me-for-woocommerce');

        $this->qr_code_service = new QrCodeService();
        $this->bitcoin_address_service = new BitcoinAddressService();
        $this->db_statements_service = new PayCryptoMeDBStatementsService();

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title') ?: __('Pay with Bitcoin', 'paycrypto-me-for-woocommerce');
        $this->description = $this->get_option('description') ?: __('Use directly your Bitcoin wallet to pay. Place the order to view the QR code and payment instructions.', 'paycrypto-me-for-woocommerce');
        $this->enabled = $this->get_option('enabled', 'yes');
        $this->hide_for_non_admin_users = $this->get_option('hide_for_non_admin_users', 'no');
        $this->debug_log = $this->get_option('debug_log', 'yes');
        $this->configured_networks = $this->get_option('configured_networks', array());
        $this->payment_timeout_hours = $this->get_option('payment_timeout_hours', '1');
        $this->payment_number_confirmations = $this->get_option('payment_number_confirmations', '2');

        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'render_admin_order_details_section'));
        add_action('woocommerce_order_details_before_order_table', array($this, 'render_checkout_order_details_section'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_styles'));

        do_action('paycryptome_for_woocommerce_gateway_loaded', $this);
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        add_action('wp_ajax_paycryptome_reset_derivation_index', array($this, 'ajax_reset_derivation_index'));
    }

    public function ajax_reset_derivation_index()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'paycrypto-me-for-woocommerce'), 403);
        }

        check_ajax_referer('paycrypto_me_nonce', 'security');

        $this->db_statements_service->reset_derivation_indexes();

        $this->register_paycrypto_me_log('Derivation indexes have been reset via admin panel.', 'warning');

        wp_send_json_success(__('Reset request received.', 'paycrypto-me-for-woocommerce'));
    }

    public function process_admin_options()
    {
        if (isset($_POST['paycrypto_me_nonce'])) {
            $nonce = isset( $_POST['paycrypto_me_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['paycrypto_me_nonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'paycrypto_me_settings' ) ) {
                wp_die( esc_html__( 'Security check failed', 'paycrypto-me-for-woocommerce' ) );
            }
        } else {
            $wpnonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $wpnonce, 'woocommerce-settings' ) ) {
                wp_die( esc_html__( 'Security check failed', 'paycrypto-me-for-woocommerce' ) );
            }
        }

        $selected_network = isset( $_POST['woocommerce_paycrypto_me_selected_network'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_paycrypto_me_selected_network'] ) ) : null;
        $network_identifier = isset( $_POST['woocommerce_paycrypto_me_network_identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_paycrypto_me_network_identifier'] ) ) : '';
        $network_config = $this->get_network_config($selected_network);

        if (empty($network_identifier)) {
            /* translators: %s is the field label being validated, e.g. "Wallet xPub". */
            $format = esc_html__( 'Please enter a valid %s.', 'paycrypto-me-for-woocommerce' );
            \WC_Admin_Settings::add_error( sprintf( $format, esc_html( $network_config['field_label'] ) ) );
            return false;
        }

        if (!$this->validate_network_identifier($selected_network, $network_identifier)) {
            /* translators: %s is the field label being validated, e.g. "Wallet xPub". */
            $format = esc_html__( 'The %s provided is not valid for the selected network.', 'paycrypto-me-for-woocommerce' );
            \WC_Admin_Settings::add_error( sprintf( $format, esc_html( $network_config['field_label'] ) ) );
            return false;
        }

        return parent::process_admin_options();
    }

    public function get_available_networks()
    {
        return array(
            'mainnet' => array(
                'name' => 'Bitcoin Mainnet',
                'address_prefix' => array('1', '3', 'bc1'),
                'xpub_prefix' => array('xpub', 'ypub', 'zpub'),
                'testnet' => false,
                'field_type' => 'text',
                'field_label' => __('Wallet xPub', 'paycrypto-me-for-woocommerce'),
                'field_placeholder' => 'e.g., xpub6, ypub6, zpub6...',
            ),
            'testnet' => array(
                'name' => 'Bitcoin Testnet',
                'address_prefix' => array('m', 'n', '2', 'tb1'),
                'xpub_prefix' => array('tpub', 'upub', 'vpub'),
                'testnet' => true,
                'field_type' => 'text',
                'field_label' => __('Testnet Wallet xPub', 'paycrypto-me-for-woocommerce'),
                'field_placeholder' => 'e.g., tpub6, upub6, vpub6...',
            ),
            // 'lightning' => array(
            //     'name' => 'Lightning Network',
            //     'address_prefix' => array('lnbc', 'lntb', 'lnbcrt'),
            //     'xpub_prefix' => array(),
            //     'testnet' => false,
            //     'field_type' => 'email',
            //     'field_label' => __('Lightning Address', 'paycrypto-me-for-woocommerce'),
            //     'field_placeholder' => 'e.g., payments@yourstore.com',
            // ),
        );
    }

    public function get_available_cryptocurrencies($network = null)
    {
        return ['BTC']; //@NOTE: all networks using same crypto.
    }

    public function check_cryptocurrency_support($currency, $network = null)
    {
        $normalized_currency = strtoupper($currency);
        $available_cryptos = $this->get_available_cryptocurrencies($network);
        return \in_array($normalized_currency, $available_cryptos, true);
    }

    public function get_configured_networks()
    {
        return $this->configured_networks;
    }

    public function get_network_config($network_type = null)
    {
        $available_networks = $this->get_available_networks();
        if ($network_type && isset($available_networks[$network_type])) {
            return $available_networks[$network_type];
        }

        return $available_networks['mainnet'];
    }

    public function init_form_fields()
    {
        $available_networks = $this->get_available_networks();
        $network_options = array();
        foreach ($available_networks as $key => $network) {
            $network_options[$key] = $network['name'];
        }

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'paycrypto-me-for-woocommerce'),
                'label' => __('Enable PayCrypto.Me.', 'paycrypto-me-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'paycrypto-me-for-woocommerce'),
                'type' => 'text',
                'description' => __('Payment method name displayed on Checkout page.', 'paycrypto-me-for-woocommerce'),
                'default' => __('Pay with Bitcoin', 'paycrypto-me-for-woocommerce'),
            ),
            'description' => array(
                'title' => __('Description', 'paycrypto-me-for-woocommerce'),
                'type' => 'textarea',
                'description' => __('Payment method description displayed on Checkout page.', 'paycrypto-me-for-woocommerce'),
                'default' => __('Pay directly from your Bitcoin wallet. Place your order to view the QR code and payment instructions.', 'paycrypto-me-for-woocommerce'),
            ),

            'selected_network' => array(
                'title' => __('Network', 'paycrypto-me-for-woocommerce'),
                'type' => 'select',
                'options' => $network_options,
                'description' => __('Select the network for payments.', 'paycrypto-me-for-woocommerce'),
                'default' => 'mainnet',
                'required' => true,
            ),

            'network_identifier' => array(
                'title' => __('Network Identifier', 'paycrypto-me-for-woocommerce'),
                'type' => 'text',
                'default' => '',
                'required' => true,
                'description' => __('Tip: It is always preferable to use the wallet xPub rather than a wallet address for Bitcoin payments.', 'paycrypto-me-for-woocommerce'),
                'custom_attributes' => array('maxlength' => 255)
            ),
            'payment_timeout_hours' => array(
                'title' => __('Payment Timeout (hours)', 'paycrypto-me-for-woocommerce'),
                'type' => 'number',
                'description' => __('Max time (in hours) to wait to confirm payment before the order expires.', 'paycrypto-me-for-woocommerce'),
                'custom_attributes' => array('min' => '1', 'step' => '1', 'max' => '72'),
                'default' => '24'
            ),
            'payment_number_confirmations' => array(
                'title' => __('Payment number of confirmations', 'paycrypto-me-for-woocommerce'),
                'type' => 'number',
                'description' => __('Tip: To ensure the Bitcoin payment has been made, recommended wait for 3 confirmations.', 'paycrypto-me-for-woocommerce'),
                'custom_attributes' => array('min' => '1', 'step' => '1', 'max' => '6'),
                'default' => '3'
            ),
            'hide_for_non_admin_users' => array(
                'title' => __('Hide for Non-Admin Users', 'paycrypto-me-for-woocommerce'),
                'label' => __('Show only for administrators.', 'paycrypto-me-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('If enabled, only administrators will see the payment method on Checkout page.', 'paycrypto-me-for-woocommerce'),
            ),
            'debug_log' => array(
                'title' => __('Debug', 'paycrypto-me-for-woocommerce'),
                'label' => __('Enable debugging messages', 'paycrypto-me-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => __('Debug logs will be saved to WooCommerce > Status > Logs.', 'paycrypto-me-for-woocommerce'),
            ),
            'paycrypto_danger_area' => array(
                'type' => 'title',
                'title' => __('Danger Area', 'paycrypto-me-for-woocommerce'),
                'description' => '
                <div class="paycrypto-danger-box">
                    <strong>Warning:</strong> ' . __('Resetting the payment derivation index will lead to the reuse of addresses and loss of past data. Proceed with caution and ensure you understand the implications.', 'paycrypto-me-for-woocommerce') . '
                    <br>
                    <button type="button" id="paycrypto-me-reset-derivation-index" class="button paycrypto-danger-btn" style="margin-top: 8px;">Reset payment address derivation index</button>
                </div>
                ',
            ),
            'paycrypto_me_donate' => array(
                'type' => 'title',
                'title' => __('Support the development!', 'paycrypto-me-for-woocommerce'),
                'description' => '<div class="paycrypto-support-box">
                    <div>
                        <img src="' . WC_PayCryptoMe::plugin_url() . '/assets/wallet_address_qrcode.png">
                    </div>
                    <div>
                        <strong>Enjoying the plugin?</strong> Send some BTC to support:
                        <div style="display: flex; align-items: center; margin-top: 8px;">
                            <span id="btc-address-admin" class="support-content">' . esc_html($this->support_btc_address) . '</span>
                            <button type="button" id="copy-btc-admin" class="support-btn">Copy</button>
                        </div>
                    </div>
                </div>',
            ),
        );
    }

    public function payment_fields()
    {
        wc_get_template(
            'checkout/paycrypto-me-checkout-option.php',
            array(
                'debug_log' => $this->debug_log,
                'description' => $this->description,
                'crypto_currency' => $this->get_available_cryptocurrencies()[0] ?? '',
            ),
            '',
            WC_PayCryptoMe::plugin_abspath() . 'templates/'
        );
    }

    public function render_admin_order_details_section($order)
    {
        echo '<style>';
        echo '.paycrypto-me-order-details { clear: both }';
        echo '.paycrypto-me-order-details h3 { margin: 0 0 10px 0 !important; padding-top: 10px !important; }';
        echo '</style>';

        $this->render_checkout_order_details_section($order);
    }

    public function render_checkout_order_details_section($order)
    {
        $logo_path = WC_PayCryptoMe::plugin_abspath() . 'assets/paycrypto-me-icon.png';

        $paycrypto_me_payment_address = $order->get_meta('_paycrypto_me_payment_address');
        $paycrypto_me_payment_uri = $order->get_meta('_paycrypto_me_payment_uri');
        $paycrypto_me_fiat_amount = $order->get_meta('_paycrypto_me_fiat_amount');
        $paycrypto_me_crypto_amount = $order->get_meta('_paycrypto_me_crypto_amount');
        $paycrypto_me_fiat_currency = $order->get_meta('_paycrypto_me_fiat_currency');
        $paycrypto_me_payment_expires_at = $order->get_meta('_paycrypto_me_payment_expires_at');
        $paycrypto_me_payment_number_confirmations = $order->get_meta('_paycrypto_me_payment_number_confirmations');
        $paycrypto_me_crypto_network = $order->get_meta('_paycrypto_me_crypto_network');
        $paycrypto_me_crypto_currency = $order->get_meta('_paycrypto_me_crypto_currency');

        $paycrypto_me_payment_qr_code = $this->qr_code_service->generate_qr_code_data_uri($paycrypto_me_payment_uri, $logo_path);

        $paycrypto_me_crypto_network_label = match ($paycrypto_me_crypto_network) {
            'mainnet' => __('On-Chain', 'paycrypto-me-for-woocommerce'),
            'testnet' => 'Testnet',
            'lightning' => 'Lightning',
            default => $paycrypto_me_crypto_network,
        };

        if ($paycrypto_me_payment_address) {
            wc_get_template(
                'order-details/paycrypto-me-order-details.php',
                compact(
                    'paycrypto_me_payment_address',
                    'paycrypto_me_payment_qr_code',
                    'paycrypto_me_payment_uri',
                    'paycrypto_me_fiat_amount',
                    'paycrypto_me_crypto_amount',
                    'paycrypto_me_fiat_currency',
                    'paycrypto_me_payment_expires_at',
                    'paycrypto_me_payment_number_confirmations',
                    'paycrypto_me_crypto_network_label',
                    'paycrypto_me_crypto_network',
                    'paycrypto_me_crypto_currency'
                ),
                '',
                WC_PayCryptoMe::plugin_abspath() . 'templates/'
            );
        }
    }

    public function generate_settings_html($form_fields = array(), $echo = true)
    {
        $html = parent::generate_settings_html($form_fields, false);

        $nonce_field = wp_nonce_field('paycrypto_me_settings', 'paycrypto_me_nonce', true, false);
        $html = str_replace('</table>', $nonce_field . '</table>', $html);

        if ($echo) {
            echo wp_kses_post( $html );
        }

        return $html;
    }

    public function admin_enqueue_scripts()
    {
        $screen = get_current_screen();

        // We only read the admin `section` GET parameter to decide which scripts/styles
        // to enqueue for the settings page. This is not processing form submission
        // data; the actual settings processing is protected by nonces in
        // `process_admin_options()`. Marking as intentionally safe for static checks.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin-only context, sanitized input, settings submission is nonce-verified elsewhere.
        $section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
        if ($screen && $screen->id === 'woocommerce_page_wc-settings' && $section === $this->id) {
            wp_enqueue_style(
                'paycrypto-me-admin',
                WC_PayCryptoMe::plugin_url() . '/assets/paycrypto-me-admin.css',
                array(),
                filemtime(WC_PayCryptoMe::plugin_abspath() . 'assets/paycrypto-me-admin.css')
            );
            wp_enqueue_script(
                'paycrypto-me-admin',
                WC_PayCryptoMe::plugin_url() . '/assets/paycrypto-me-admin.js',
                array(),
                filemtime(WC_PayCryptoMe::plugin_abspath() . 'assets/paycrypto-me-admin.js'),
                true
            );
            wp_localize_script(
                'paycrypto-me-admin',
                'PayCryptoMeAdminData',
                array(
                    'networks' => $this->get_available_networks(),
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('paycrypto_me_nonce'),
                )
            );
        }

        if ($screen && $screen->id === 'woocommerce_page_wc-orders' || $screen->id === 'shop_order') {
            wp_enqueue_style(
                'paycrypto-me-admin-order-details',
                WC_PayCryptoMe::plugin_url() . '/assets/css/frontend/paycrypto-me-order-details.css',
                array(),
                filemtime(WC_PayCryptoMe::plugin_abspath() . 'assets/css/frontend/paycrypto-me-order-details.css')
            );
        }

    }

    public function is_available()
    {
        if ('yes' !== $this->enabled) {
            return false;
        }
        if ('yes' === $this->hide_for_non_admin_users && !current_user_can('manage_options')) {
            return false;
        }

        if (empty($this->get_option('selected_network'))) {
            return false;
        }

        if (empty($this->get_option('network_identifier'))) {
            return false;
        }

        return true;
    }

    public function process_pre_order_payment($order)
    {
        return PaymentProcessor::instance()->process_payment($order->get_id(), $this);
    }

    public function process_payment($order_id)
    {
        return PaymentProcessor::instance()->process_payment($order_id, $this);
    }

    public function enqueue_checkout_styles()
    {
        if (is_checkout() || is_wc_endpoint_url('order-pay')) {
            $css_file = WC_PayCryptoMe::plugin_url() . '/assets/css/frontend/paycrypto-me-styles.css';
            $css_path = WC_PayCryptoMe::plugin_abspath() . 'assets/css/frontend/paycrypto-me-styles.css';

            if (file_exists($css_path)) {
                wp_enqueue_style(
                    'paycrypto-me-checkout',
                    $css_file,
                    array(),
                    filemtime($css_path)
                );
            }
        }

        if (is_order_received_page() || is_account_page()) {
            $css_file = WC_PayCryptoMe::plugin_url() . '/assets/css/frontend/paycrypto-me-order-details.css';
            $css_path = WC_PayCryptoMe::plugin_abspath() . 'assets/css/frontend/paycrypto-me-order-details.css';

            if (file_exists($css_path)) {
                wp_enqueue_style(
                    'paycrypto-me-order-details',
                    $css_file,
                    array(),
                    filemtime($css_path)
                );
            }
        }
    }

    public function register_paycrypto_me_log($message, $level = 'info')
    {
        if ($this->debug_log === 'yes') {
            $safe_message = wp_strip_all_tags( (string) $message );
            \PayCryptoMe\WooCommerce\WC_PayCryptoMe::log( $safe_message, $level );
        }
    }

    private function validate_xpub_address($network_type, $identifier)
    {
        $network = $network_type === 'testnet'
            ? NetworkFactory::bitcoinTestnet()
            : NetworkFactory::bitcoin();

        try {
            if ($ok = $this->bitcoin_address_service->validate_extended_pubkey($identifier, $network)) {
                return $ok;
            }
        } catch (\Throwable $th) {
        }

        return false;
    }

    private function validate_network_identifier($network_type, $identifier)
    {
        if ($network_type === 'lightning' && is_email($identifier)) {
            return true;
        }

        if ($network_type !== 'lightning') {
            $network = $network_type === 'testnet' ? NetworkFactory::bitcoinTestnet() : NetworkFactory::bitcoin();

            if ($this->validate_xpub_address($network_type, $identifier)) {
                return true;
            }

            if ($this->bitcoin_address_service->validate_bitcoin_address($identifier, $network)) {
                return true;
            }
        }

        $this->register_paycrypto_me_log(
            \sprintf('Network identifier validation failed for %s: `%s`', $network_type, $this->mask_identifier_for_log($network_type, $identifier)),
            'error'
        );

        return false;
    }

    private function mask_identifier_for_log($network_type, $identifier)
    {
        if ($network_type === 'lightning') {
            $parts = explode('@', $identifier);
            if (\count($parts) === 2) {
                return $parts[0] . '@' . substr($parts[1], 0, 1) . (strpos($parts[1], '.') !== false ?
                    '***.' . substr(strrchr($parts[1], '.'), 1) :
                    '***');
            }
        } else {
            if (\strlen($identifier) > 10) {
                return substr($identifier, 0, 6) . '...' . substr($identifier, -4);
            }
        }
        return $identifier;
    }
}