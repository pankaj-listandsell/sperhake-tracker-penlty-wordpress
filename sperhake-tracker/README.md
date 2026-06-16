# Sperhake Vehicle Tracking & Penalty Payment

Production-ready WordPress plugin for **Abschleppdienst Sperhake**. Vehicle owners
look up a towed vehicle by license plate, view its status and storage location,
and pay any outstanding penalty online through **Stripe Checkout**. Payments are
confirmed via a signed Stripe webhook, a PDF receipt is generated and emailed,
and the transaction is forwarded to an external API with automatic retries.

---

## 1. Requirements

| Component | Version |
|-----------|---------|
| WordPress | 6.4+ |
| PHP | 8.2+ (tested on 8.4) |
| MySQL / MariaDB | 5.7+ / 10.4+ |
| Composer | for installing the Stripe SDK + DomPDF |
| PHP extensions | `openssl` or `sodium`, `curl`, `mbstring`, `gd`/`dom` (DomPDF) |

## 2. Installation

```bash
# 1. Copy the plugin into wp-content/plugins
wp-content/plugins/sperhake-tracker/

# 2. Install third-party libraries (Stripe SDK + DomPDF)
cd wp-content/plugins/sperhake-tracker
composer install --no-dev --optimize-autoloader

# 3. Activate
wp plugin activate sperhake-tracker      # or via WP Admin → Plugins
```

On activation the plugin:

* creates the `wp_sperhake_transactions` and `wp_sperhake_logs` tables (`dbDelta`),
* generates a per-site encryption key (stored non-autoloaded; override with
  `SPERHAKE_ENCRYPTION_KEY` in `wp-config.php` for extra hardening),
* creates a **protected** uploads folder `uploads/sperhake-receipts/` (`.htaccess`
  deny-all) for PDFs,
* schedules a 5-minute WP-Cron job for API-sync retries and data retention.

> **Tip:** for production reliability replace WP-Cron with a real cron hitting
> `wp-cron.php`, and set `define( 'DISABLE_WP_CRON', true );`.

## 3. Configuration (WP Admin → **Sperhake Tracker**)

1. **API Settings** – Vehicle Tracking API URL, API Key, API Secret (used to sign
   requests with an HMAC-SHA256 `X-Signature` header), custom headers, the
   forward webhook URL, and timeout. Keys are stored **encrypted**. This page also
   has a **Frontend Search Security** section:
   * **Cloudflare Turnstile** – paste your Site Key + Secret Key to switch on the
     CAPTCHA. Leave blank to disable. (Get keys at Cloudflare → Turnstile.)
   * **Require reference number** – forces a second identifier (e.g. *Case Number*)
     alongside the plate so visitors can't enumerate arbitrary plates. The field
     label is configurable and the value is sent to your API as `reference`; the
     API must reject a plate+reference that don't match (HTTP 404).
2. **Stripe Settings** – Mode (test/live), Publishable Key, Secret Key, Webhook
   Signing Secret, currency, optional SEPA. The page shows your **webhook
   endpoint URL** — copy it into Stripe.
3. **Email Settings** – From name/address, subject, logo URL, attach-PDF toggle.

### Stripe webhook setup

In the Stripe Dashboard → Developers → Webhooks → *Add endpoint*:

* **Endpoint URL:** `https://your-site/wp-json/sperhake/v1/stripe-webhook`
* **Events:** `checkout.session.completed`,
  `checkout.session.async_payment_succeeded`,
  `checkout.session.async_payment_failed`, `checkout.session.expired`
* Copy the **Signing secret** into *Stripe Settings → Webhook Signing Secret*.

## 4. Frontend usage

Place the shortcode on any page:

```
[sperhake_vehicle_search]
```

Flow: customer enters a plate → AJAX search → result card with status & storage
details → **Pay Now** (only when `penalty_amount > 0`) → Stripe Checkout →
returns to the same page showing the confirmation panel + receipt download.

## 5. Architecture

```
sperhake-tracker/
├── sperhake-tracker.php        # Bootstrap, constants, autoloaders, hooks
├── uninstall.php               # Full cleanup (respects "preserve data" flag)
├── composer.json               # stripe/stripe-php, dompdf/dompdf, PSR-4 map
├── includes/
│   ├── Autoloader.php          # First-party PSR-4 fallback autoloader
│   ├── Plugin.php              # Service container + wiring
│   ├── Activator / Deactivator
│   ├── Database/Schema.php     # dbDelta table definitions
│   ├── Database/TransactionRepository.php   # All prepared-statement DB access
│   ├── Database/SearchLogRepository.php      # Search audit log (abuse detection)
│   ├── Security/Encryption.php # sodium/openssl secret encryption
│   ├── Security/Turnstile.php  # Cloudflare Turnstile server verification
│   ├── Logging/Logger.php      # DB-backed logger with secret redaction
│   ├── Support/Options.php     # Typed settings accessor (auto-decrypts)
│   ├── Support/Plate.php       # Plate normalisation/validation
│   ├── Cron/RetryQueue.php     # API-sync retries + retention cleanup
│   └── GDPR/PrivacyManager.php # Export/erase/policy integration
├── admin/
│   ├── AdminMenu.php           # Menu, pages, CSV export
│   ├── Settings/SettingsRegistry.php   # Settings API + encryption-on-save
│   └── Pages/*.php             # dashboard / transactions / logs views
├── frontend/
│   ├── Assets.php · Shortcode.php · AjaxHandler.php
├── api/
│   ├── VehicleApiClient.php    # POST /vehicle/search (signed)
│   └── ExternalApiClient.php   # POST /payment-completed (signed)
├── payments/
│   ├── StripeGateway.php       # Creates Checkout Sessions (server-trusted amount)
│   └── WebhookController.php   # Verifies signature → the only payment source of truth
├── emails/Mailer.php           # HTML email + PDF attachment
├── pdf/ReceiptGenerator.php    # DomPDF receipt, stored securely
├── templates/                  # Themeable views (override in theme/sperhake-tracker/)
└── assets/                     # CSS + vanilla-JS (no jQuery dependency)
```

### Security model (highlights)

* **Amounts are never trusted from the browser.** When *Pay Now* is clicked the
  server re-queries the Vehicle API for the authoritative penalty before creating
  the Checkout Session.
* **Payment truth comes only from the verified webhook**, never the success URL.
  The webhook verifies the Stripe signature and is idempotent. It also
  **re-validates the captured amount** against the live penalty: if the customer
  paid *less* than the current penalty (e.g. the fine was raised after checkout
  opened), the transaction is marked `paid` but flagged `review`, auto-forwarding
  to the external API is suppressed, and `sperhake_payment_amount_mismatch` fires.
* **Bot protection** via Cloudflare Turnstile, verified server-side before any API
  call (fails open only on a Cloudflare transport outage, which is logged).
* **Anti-enumeration**: optional required reference number gates every lookup.
* **Duplicate-payment prevention**: before creating a Checkout Session the gateway
  reuses an existing *open* session for the same plate+amount, so three tabs →
  one session, not three.
* **Search audit log** (`wp_sperhake_search_logs`): every search records plate,
  salted-IP, result and timestamp for abuse detection (viewable under System Logs,
  auto-pruned by retention).
* **Secrets at rest are encrypted** (libsodium / AES-256-GCM) and never logged
  (the logger redacts known secret keys).
* **Every DB query is a prepared statement** / uses the `$wpdb` format API.
* **Nonces + capability checks** on all admin and AJAX actions; output is escaped,
  input sanitised; receipt downloads use a per-row unguessable token and a
  path-traversal-safe resolver.
* **Open-redirect protection** on the Stripe return URL (same-origin only).
* Lightweight per-IP rate limiting on search to deter plate enumeration.

### GDPR

* Suggested privacy-policy text registered with the WP Privacy guide.
* Personal-data **exporter** and **eraser** (anonymises name/email, keeps
  financial fields for accounting/legal duties).
* Configurable **retention period**; the cron job deletes expired records.
* Uninstall removes data unless *preserve data* is enabled.

## 6. External API contracts

**Vehicle search** — `POST {api_url}/vehicle/search`

```json
{ "license_plate": "AB-CD-123" }
```

Expected response fields (all optional except status): `license_plate`,
`owner_name`, `vehicle_id`, `status`, `towed_date`, `towed_time`,
`pickup_address`, `towing_location`, `storage_yard_name`,
`storage_yard_address`, `current_location`, `contact_number`,
`release_instructions`, `penalty_amount`, `currency`.

Route map fields — the "from" and "to" endpoints used to draw the
relocation route: `address_a` (origin, falls back to `pickup_address`)
with optional `address_a_lat`/`address_a_lng`, and `address_b`
(destination, populated once paid) with optional
`address_b_lat`/`address_b_lng`. Coordinates are preferred over the
plain address for an exact route.

**Payment completed** — `POST {webhook_url}`

```json
{
  "transaction_id": "TRX-XXXXXXXXXX",
  "license_plate": "AB-CD-123",
  "customer_name": "John Doe",
  "amount": "250.00",
  "currency": "EUR",
  "payment_status": "paid",
  "payment_date": "2026-01-01"
}
```

Both outbound requests include `X-API-Key` and an `X-Signature`
(`hmac_sha256(body, api_secret)`) header for server-side verification.

## 7. Developer hooks

| Hook | Type | Fires |
|------|------|-------|
| `sperhake_tracker_booted` | action | after all services register |
| `sperhake_payment_completed` | action | after a webhook-confirmed payment (`$transaction`) |

Templates under `templates/` can be overridden by copying them into
`your-theme/sperhake-tracker/`.

## 8. Uninstall

Deleting the plugin runs `uninstall.php`: clears cron, deletes generated PDFs,
removes options (incl. the encryption key) and drops the custom tables — unless
*Preserve data on uninstall* is enabled in GDPR settings.
