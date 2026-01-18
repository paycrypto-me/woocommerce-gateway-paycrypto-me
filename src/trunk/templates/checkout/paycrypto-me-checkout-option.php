<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<p><?php echo esc_html( $description ); ?></p>
<input type="hidden" name="paycrypto_me_crypto_currency" value="<?php echo esc_attr( $crypto_currency ); ?>">