<?php
if (!defined('ABSPATH')) {
    exit;
}

if ($paycrypto_me_payment_address): ?>
    <section class="wc-block-order-confirmation-billing-address paycrypto-me-order-details">
        <h3><?php esc_html_e('Payment Details', 'woocommerce-gateway-paycrypto-me'); ?></h3>

        <div class="paycrypto-me-order-details__container">
            <div class="paycrypto-me-order-details__wrapper">
                <small><?php esc_html_e('Fiat Amount:', 'woocommerce-gateway-paycrypto-me'); ?></small>
                <small><?php echo wp_kses_post( wc_price( $paycrypto_me_fiat_amount, array( 'currency' => $paycrypto_me_fiat_currency ) ) ); ?></small>
            </div>
            <?php
            if (!empty($paycrypto_me_crypto_amount)) {
                ?>
                <div class="paycrypto-me-order-details__wrapper">
                    <small>
                        <?php esc_html_e('Crypto Amount:', 'woocommerce-gateway-paycrypto-me'); ?>
                    </small>
                    <small><?php echo esc_html( number_format_i18n( (float) $paycrypto_me_crypto_amount, 8 ) . ' ' . $paycrypto_me_crypto_currency ); ?></small>
                </div>
                <?php
                }
            ?>
            <div class="paycrypto-me-order-details__wrapper">
                <small>
                    <?php esc_html_e('Crypto Network:', 'woocommerce-gateway-paycrypto-me'); ?>
                </small>
                <div class="paycrypto-me-network-switch">
                    <label class="paycrypto-me-network-<?php echo esc_attr($paycrypto_me_crypto_network); ?>-label">
                        <?php echo esc_html($paycrypto_me_crypto_network_label); ?>
                    </label>
                </div>
            </div>
            <div
                class="paycrypto-me-order-details__wrapper paycrypto-me-order-details__wrapper--qr-code"
                style="margin-top: 8px; justify-content: center; line-height: 1;">
                <small
                    style="font-weight: 700;"><?php esc_html_e('Scan QR Code to Pay:', 'woocommerce-gateway-paycrypto-me'); ?></small>
            </div>
            <div class="paycrypto-me-order-details__qr-code-image">
                <img src="<?php echo $paycrypto_me_payment_qr_code ?>"
                    alt="<?php esc_attr_e('QR Code for Payment', 'woocommerce-gateway-paycrypto-me'); ?>" />
            </div>
            <div class="paycrypto-me-order-details__wrapper paycrypto-me-order-details__wrapper--address">
                <small
                    class="paycrypto-me-order-details__address"><?php echo esc_html($paycrypto_me_payment_address); ?></small>
                    <button
                        class="paycrypto-me-order-details__copy-address-button"
                        data-address="<?php echo esc_attr($paycrypto_me_payment_address); ?>"
                        onclick="window.navigator.clipboard.writeText(this.getAttribute('data-address')) && alert('<?php esc_html_e('Payment address copied to clipboard.', 'woocommerce-gateway-paycrypto-me'); ?>');">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1z"/>
                        </svg>
                    </button>
            </div>

            <a class="woocommerce-button wp-element-button paycrypto-me-order-details__button paycrypto-me-order-details__open-wallet-button"
                class="woocommerce-button wp-element-button paycrypto-me-order-details__button paycrypto-me-order-details__open-wallet-button"
                href="<?php echo $paycrypto_me_payment_uri ?>" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('open your wallet app', 'woocommerce-gateway-paycrypto-me'); ?>
            </a>
        </div>

    </section>
<?php endif; ?>