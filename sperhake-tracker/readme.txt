=== Sperhake Vehicle Tracking & Penalty Payment ===
Contributors: abschleppdienstsperhake
Tags: towing, vehicle, stripe, payment, penalty
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Vehicle lookup by license plate, towing status, and secure online penalty payment via Stripe for Abschleppdienst Sperhake.

== Description ==

Vehicle owners search a towed vehicle by license plate, view status and storage
location, and pay outstanding penalties via Stripe Checkout. Payments are
confirmed by a signed Stripe webhook (never the success URL), a PDF receipt is
generated and emailed, and the transaction is forwarded to an external API with
automatic retries.

Use the shortcode `[sperhake_vehicle_search]` on any page.

== Installation ==

1. Upload the `sperhake-tracker` folder to `/wp-content/plugins/`.
2. Run `composer install` inside the plugin folder (installs the Stripe SDK and DomPDF).
3. Activate the plugin through the 'Plugins' menu.
4. Configure API, Stripe and Email settings under "Sperhake Tracker".
5. Add the Stripe webhook endpoint shown on the Stripe Settings page.

== Frequently Asked Questions ==

= Where are API keys and Stripe secrets stored? =
Encrypted at rest (libsodium or AES-256-GCM) in the options table. They are never
written to logs.

= How is the payment amount protected? =
The penalty is re-fetched from the Vehicle API server-side before the Stripe
Checkout Session is created, so a tampered browser value cannot change the price.

== Changelog ==

= 1.2.0 =
* Integrated the relocation API: GET /search (by plate) and POST /{case_id}/paid on payment.
* Post-payment view now shows the relocation destination with a "Track on Map" link.
* Added a "Request Invoice" action (POST /{case_id}/request-invoice) with optional email override.
* Paid cases no longer show the Pay button (prevents double payment).

= 1.1.0 =
* Added Cloudflare Turnstile CAPTCHA on the search form (server-verified).
* Added optional required reference number to prevent plate enumeration.
* Webhook now re-validates the captured amount against the live penalty.
* Duplicate-session prevention: reuses an open Checkout Session per plate.
* New wp_sperhake_search_logs table + System Logs audit viewer.
* Webhook returns 403 (not 500) when the signing secret is unconfigured.

= 1.0.0 =
* Initial release.
