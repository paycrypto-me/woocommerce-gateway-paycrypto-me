/**
 * PayCrypto.Me WooCommerce Blocks Integration
 * 
 * Provides payment method integration for WooCommerce Blocks (Cart & Checkout blocks)
 * 
 * @package PayCrypto.Me
 * @version 0.1.0
 */

import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { createElement } from '@wordpress/element';
import { useEffect } from 'react';

const settings = getSetting('paycrypto_me_data', {});
const defaultLabel = __('Pay with Bitcoin', 'paycrypto-me-for-woocommerce');

const label = decodeEntities(settings.title) || defaultLabel;

const Content = ({ eventRegistration, emitResponse }) => {
    const { onPaymentSetup } = eventRegistration;

    const description = decodeEntities(settings.description || '');

    useEffect(() => {

        const unsubscribe = onPaymentSetup(async () => {
            const paycrypto_me_crypto_currency = settings.crypto_currency;

            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: { paycrypto_me_crypto_currency }
                },
            };
        });

        return () => {
            unsubscribe();
        };
    }, [onPaymentSetup, emitResponse, settings.crypto_currency]);

    if (!description) {
        return null;
    }

    return createElement('div', {
        className: 'wc-paycrypto-me-description',
        dangerouslySetInnerHTML: { __html: description }
    });
};

const Label = ({ components }) => {
    const { PaymentMethodLabel } = components;

    return createElement(PaymentMethodLabel, {
        text: label,
        className: 'wc-paycrypto-me-label',
    });
};

const ariaLabel = label;

const canMakePayment = () => {
    return true;
};

const PayCryptoMePaymentMethod = {
    name: 'paycrypto_me',
    label: createElement(Label),
    content: createElement(Content),
    edit: createElement(Content),
    canMakePayment,
    ariaLabel,
    supports: {
        features: settings.supports || ['products'],
    }
};

registerPaymentMethod(PayCryptoMePaymentMethod);

if (settings.debug_log || (typeof process !== 'undefined' && process.env && process.env.NODE_ENV === 'development')) {
    console.log('PayCrypto.Me payment method registered:', PayCryptoMePaymentMethod);
    console.log('PayCrypto.Me settings:', settings);
}