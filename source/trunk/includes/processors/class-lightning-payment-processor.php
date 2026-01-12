<?php
/**
 * PayCrypto.Me Gateway for WooCommerce
 *
 * @package     WooCommerce\PayCryptoMe
 * @class       LightningPaymentProcessor
 * @extends     PaymentProcessor
 * @author      PayCrypto.Me
 * @copyright   2025 PayCrypto.Me
 * @license     GNU General Public License v3.0
 */

namespace PayCryptoMe\WooCommerce;

\defined('ABSPATH') || exit;

class LightningPaymentProcessor extends AbstractPaymentProcessor
{
    public function process(\WC_Order $order, array $payment_data): array
    {
        throw new PayCryptoMeException('Lightning Network payments are not yet implemented.');
    }
}