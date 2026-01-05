document.addEventListener('DOMContentLoaded', function () {
    var networkSelect = document.querySelector('select[name="woocommerce_paycrypto_me_selected_network"]');
    var identifierLabel = document.querySelector('label[for="woocommerce_paycrypto_me_network_identifier"]');
    var identifierInput = document.getElementById('woocommerce_paycrypto_me_network_identifier');

    if (!networkSelect || !identifierLabel || !identifierInput) return;

    function networkOnChange(...rest) {
        var selected = networkSelect.value;

        var networks = window.PayCryptoMeAdminData?.networks || {};

        var field_placeholder = networks[selected]?.field_placeholder || '';
        var field_label = networks[selected]?.field_label || 'Network Identifier';
        var field_type = networks[selected]?.field_type || 'text';

        identifierInput.placeholder = field_placeholder;
        identifierLabel.textContent = field_label;
        identifierInput.type = field_type;

        if (rest.length > 0) {
            identifierInput.value = '';
            identifierInput.focus();
        }
    }

    networkSelect.addEventListener('change', networkOnChange);
    networkOnChange();

    //

    var btn = document.getElementById('copy-btc-admin');
    if (btn) {
        btn.addEventListener('click', function () {
            var address = document.getElementById('btc-address-admin').textContent;
            navigator.clipboard.writeText(address).then(function () {
                alert('Address copied!');
            });
        });
    }

    var resetBtn = document.getElementById('paycrypto-me-reset-derivation-index');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (confirm('Are you sure you want to reset the payment address derivation index? This action cannot be undone.')) {
                fetch(window.PayCryptoMeAdminData.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        action: 'paycrypto_me_reset_derivation_index',
                        security: window.PayCryptoMeAdminData.nonce
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Derivation index has been reset successfully.');
                        } else {
                            alert('Error: ' + data.data);
                        }
                    })
                    .catch(error => {
                        alert('An unexpected error occurred.');
                        console.error('Error:', error);
                    });
            }
        });
    }
});
