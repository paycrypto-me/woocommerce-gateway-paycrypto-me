# PayCrypto.Me for WooCommerce

Accept Bitcoin (on-chain) and Lightning payments in your WooCommerce store with PayCrypto.Me. This plugin provides a secure, non-custodial checkout experience and automated order handling.

## Highlights

- Accept Bitcoin and Lightning payments
- Non-custodial — funds go directly to merchant wallets
- Seamless checkout integration with QR code support
- Compatible with WooCommerce Blocks and Custom Order Tables
- Translation-ready and includes sample translations
- Debug logging via WooCommerce logger

## Quick Start

1. Upload the plugin folder to `/wp-content/plugins/` or install via GitHub/ZIP.
2. Activate the plugin in WordPress admin (Plugins → Installed Plugins).
3. Go to **WooCommerce → Settings → Payments** and enable **PayCrypto.Me**.
4. Configure your wallet identifier (xPub / on-chain address / Lightning address) and preferences.

## Configuration Notes

- Recommended for Bitcoin: use an xPub to derive unique receiving addresses.
- Payment timeout and confirmations can be customized in gateway settings.
- Use the "Hide for non-admin users" option to test without showing the payment method to customers.

## Screenshots & Assets

Images are included in the `source/assets` folder:

- `banner-1544x500.png` — plugin banner
- `banner-772x250.png` — small banner
- `screenshot-1.jpg` … `screenshot-5.jpg` — admin and checkout screenshots

## Development & Testing

- Enable testnet in settings or use a test wallet for development.
- Enable logging to inspect events in WooCommerce > Status > Logs.

## Contributing

Contributions are welcome. Please open issues for bugs or feature requests and submit pull requests with clear descriptions and tests when possible.

## Support

Visit https://paycrypto.me/ for documentation and support. For repository issues, use the GitHub issue tracker.

## License

GPLv3 — see `LICENSE` for details.
