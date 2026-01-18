<?php
/**
 * PayCrypto.Me Gateway for WooCommerce
 *
 * @package     WooCommerce\PayCryptoMe
 * @class       BitcoinPaymentProcessor
 * @extends     PaymentProcessor
 * @author      PayCrypto.Me
 * @copyright   2025 PayCrypto.Me
 * @license     GNU General Public License v3.0
 */

namespace PayCryptoMe\WooCommerce;

\defined('ABSPATH') || exit;

class BitcoinPaymentProcessor extends AbstractPaymentProcessor
{
    private BitcoinAddressService $bitcoin_address_service;
    private PayCryptoMeDBStatementsService $db;

    public function __construct(\WC_Payment_Gateway $gateway)
    {
        parent::__construct($gateway);
        $this->bitcoin_address_service = new BitcoinAddressService();
        $this->db = new PayCryptoMeDBStatementsService();
    }

    public function process(\WC_Order $order, array $payment_data): array
    {
        $xPub = $this->gateway->get_option('network_identifier');
        $network = $this->gateway->get_option('selected_network');

        $bitcoin_network = $network === 'mainnet' ?
            \BitWasp\Bitcoin\Network\NetworkFactory::bitcoin() :
            \BitWasp\Bitcoin\Network\NetworkFactory::bitcoinTestnet();

        if (empty($xPub)) {
            throw new PayCryptoMeException('Bitcoin xPub is not configured in the payment gateway settings.');
        }

        // Accept either an extended public key (xpub/ypub/zpub/...) or a single
        // static Bitcoin address (bech32/legacy). If a static address is provided
        // treat it as the payment address for this order (no derivation).
        if ($this->bitcoin_address_service->validate_bitcoin_address($xPub, $bitcoin_network)) {
            $payment_address = $xPub;

            $payment_data['payment_address'] = $payment_address;

            $message = \sprintf(
                /* translators: 1: payment address, 2: order reference number. */
                __('Payment sent to %1$s, Order Reference #%2$s', 'paycrypto-me-for-woocommerce'),
                $payment_address,
                $order->get_order_number()
            );

            $payment_data['payment_uri'] = $this->bitcoin_address_service->build_bitcoin_payment_uri(
                message: $message,
                address: $payment_address,
                amount: $payment_data['crypto_amount'],
                label: $order->get_billing_first_name(),
            );

            return $payment_data;
        }

        if (!$this->bitcoin_address_service->validate_extended_pubkey($xPub, $bitcoin_network)) {
            throw new PayCryptoMeException(
                \sprintf(
                    'Invalid Bitcoin extended public key configured: %s. Please provide a valid.',
                    esc_html(substr($xPub, 0, 4) . '...' . substr($xPub, -3))
                )
            );
        }

        try {
            $existing = $this->db->get_by_order_id((int) $order->get_id());

            if ($existing && !empty($existing['payment_address'])) {
                $payment_address = $existing['payment_address'];
                $derivation_index = $existing['derivation_index'];
            } else {

                if (!$wallet_xpub_id = $this->db->get_wallet_xpubkey_id($xPub, $network)) {
                    $wallet_xpub_id = $this->db->insert_wallet_xpubkey($xPub, $network);
                }

                if (!$wallet_xpub_id) {
                    throw new PayCryptoMeException(
                        \sprintf('Failed to persist wallet xPub for order #%s', $order->get_id())
                    );
                }

                $derivation_index = (int) $this->db->reserve_derivation_index_for_wallet((int) $wallet_xpub_id);

                $payment_address = $this->bitcoin_address_service->generate_address_from_xPub($xPub, $derivation_index, $bitcoin_network);

                $inserted = $this->db->insert_address((int) $order->get_id(), $derivation_index, $payment_address, $wallet_xpub_id);

                if ($inserted === false) {
                    $this->gateway->register_paycrypto_me_log(
                        \sprintf('Failed to persist generated address for order #%s', $order->get_id()),
                        'error'
                    );
                }
            }

            $payment_data['payment_address'] = $payment_address;
            $payment_data['derivation_index'] = $derivation_index;

            $message = \sprintf(
                /* translators: 1: payment address, 2: order reference number. */
                __('Payment sent to %1$s, Order Reference #%2$s', 'paycrypto-me-for-woocommerce'),
                $payment_address,
                $order->get_order_number()
            );

            $payment_data['payment_uri'] = $this->bitcoin_address_service->build_bitcoin_payment_uri(
                message: $message,
                address: $payment_address,
                amount: $payment_data['crypto_amount'],
                label: $order->get_billing_first_name(),
            );

        } catch (\Exception $e) {
            $clean = wp_strip_all_tags( $e->getMessage() );
            throw new PayCryptoMeException(
                \sprintf('Bitcoin Payment Processor: %s', esc_html( $clean )),
                0
            );
        }

        return $payment_data;
    }
}