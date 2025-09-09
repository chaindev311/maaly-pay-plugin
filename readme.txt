=== Maaly Pay Integration ===
Contributors: chain-dev
Tags: payments, crypto, maaly pay
Requires at least: 5.2
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 7.4

A simple WordPress admin plugin to create Maaly Pay payment requests and check transaction status.
No WooCommerce required.

== Description ==
- Admin pages:
  * Create Payment Request
  * Check Transaction Status
  * Settings (save API key)

This plugin is a SaaS client that connects to the Maaly Pay service to create payment requests and check transaction status. An API key is required.

Service: `https://maalyportal.com/` (see their documentation/terms on that site).

== Privacy ==
This plugin sends the following data from your site’s admin to the Maaly Pay API when you use it:
- merchant ID, fiat amount, currency, description, merchant transaction ID, and callback URL.

No personal data is stored locally by this plugin other than the API key option you provide. If you need to remove the stored API key, deactivating and deleting the plugin will remove it.

Suggested text for your site privacy policy:
"This site uses the Maaly Pay Integration plugin to create cryptocurrency payment requests. When an administrator creates a payment, the site sends merchant and payment request details to the Maaly Pay service (`maalyportal.com`) to generate a checkout URL. No customer personal data is processed by the plugin."

== Installation ==
1. Upload the ZIP via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to Maaly Pay → Settings and save your API key.

== Usage ==
- Create Payment: fill the form, submit, open/iframe the Checkout URL.
- Check Status: enter merchant_tx_id and submit to view status/amount.

== Changelog ==
= 1.0.0 =
Initial release.
