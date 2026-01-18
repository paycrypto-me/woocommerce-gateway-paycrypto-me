=== PayCrypto.Me for WooCommerce ===
Contributors: lucasrosa95
Tags: woocommerce, payments, crypto, bitcoin, cryptocurrencies
Donate link: https://paycrypto.me/
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accept Bitcoin and many cryptocurrencies payments in WooCommerce — a non-custodial, international-ready gateway.

== Description ==

PayCrypto.Me for WooCommerce offers a complete solution that allows your customers to pay using many cryptocurrencies in your store.

Key features:

- Accept Bitcoin (on-chain) and Lightning payments
- Non-custodial: funds go directly to merchant wallets
- Automatic order processing and payment status updates
- Compatible with WooCommerce Blocks and Custom Order Tables
- Internationalization ready (see /languages)
- Debug logging using the WooCommerce logger

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install via your deployment workflow.
2. Activate the plugin via the WordPress Plugins screen.
3. Go to WooCommerce → Settings → Payments and enable "PayCrypto.Me".
4. Configure your wallet and options (timeout, confirmations, network).
5. Testing: enable testnet mode in settings and create a test order.

Notes:
- For troubleshooting enable WooCommerce logs (WooCommerce → Status → Logs) and select `paycrypto_me`.
- The plugin is not responsible for the data provided or who accesses it. All responsibility lies with the website administrator.
- At the moment, the plugin supports only Bitcoin transactions. Support for other cryptocurrencies may be added in future plugin updates.
- The plugin currently does not manage transaction confirmations. This feature may be included in future updates.

== Screenshots ==

1. Checkout page with PayCrypto.Me option - ../assets/screenshot-1.jpg
2. Order details page showing payment details and payment QR code - ../assets/screenshot-2.jpg
3. Admin panel order details page showing payment details and payment QR code - ../assets/screenshot-3.jpg
4. Woocommerce Payment Settings listing PayCrypto.Me option - ../assets/screenshot-4.jpg
5. Admin panel PayCrypto.Me plugin settings page - ../assets/screenshot-5.jpg

== Frequently Asked Questions ==

= Which cryptocurrencies are supported? =
Bitcoin (BTC) — on-chain and Lightning. Additional networks may be supported via PayCrypto.Me.

= Where are payment logs stored? =
Uses `wc_get_logger()` with the source `paycrypto_me`. Access logs via WooCommerce → Status → Logs.

= How do I test payments? =
Use testnet mode and PayCrypto.Me test credentials. Create test orders and confirm webhook delivery.

== Changelog ==

= 0.1.0 =
* Initial public release.
* PayCrypto.Me integration for on-chain and Lightning payments.
* Support for WooCommerce Blocks and Custom Order Tables.
* Internationalization and translations included.

== Upgrade Notice ==

= 0.1.0 =
Initial release.

== Support ==

For support visit https://paycrypto.me/ or open an issue on the GitHub repository.

== Credits ==

Developed by PayCrypto.Me — https://paycrypto.me/

