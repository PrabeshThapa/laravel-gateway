# NepalPay Laravel Gateway

Unified, driver-based payment gateway integration for Nepal — eSewa, Khalti, and Nepal Payment Solution (OnePG) — for Laravel 12/13 applications.

## Installation

1. Add this package as a local path repository in your Laravel app's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../laravel-gateway"
    }
]
```

2. Require it:

```bash
composer require nepalpay/laravel-gateway:dev-main
```

3. Run the installer:

```bash
php artisan nepal-payment:install
```

This publishes the config file and database migration, runs the migration, and prints next steps. Payment gateway logos are served directly from the package.

## Configuration

Set these in your `.env`:

```env
PAYMENT_MODE=dev
PAYMENT_DEFAULT_GATEWAY=esewa

# eSewa (public sandbox credentials work out of the box)
ESEWA_DEV_MERCHANT_CODE=EPAYTEST
ESEWA_DEV_SECRET_KEY=8gBm/:&EnhH.1/q

# Khalti (get your own sandbox key at https://test-admin.khalti.com)
KHALTI_DEV_SECRET_KEY=

# Nepal Payment / OnePG (requires merchant onboarding with nepalpayment.com)
NEPALPAYMENT_DEV_MERCHANT_ID=
NEPALPAYMENT_DEV_MERCHANT_NAME=
NEPALPAYMENT_DEV_API_USERNAME=
NEPALPAYMENT_DEV_API_PASSWORD="wrap in quotes if it contains # or special chars"
NEPALPAYMENT_DEV_API_SECRET="wrap in quotes if it contains # or special chars"

# Fonepay (credentials and production base URL are provided by Fonepay)
FONEPAY_DEV_BASE_URL=https://uat-new-merchant-api.fonepay.com
FONEPAY_DEV_USERNAME=
FONEPAY_DEV_PASSWORD=
FONEPAY_DEV_PRIVATE_KEY=
FONEPAY_DEV_TERMINAL_ID=
```

Switch to `PAYMENT_MODE=live` and fill in the corresponding `_LIVE_` keys when going to production.
For Fonepay, enter the API base URL as the host only (no API path); the package appends the login and payment endpoints. The private key can be a PEM file path (absolute or relative to the Laravel app), PEM contents, or base64-encoded PKCS#8 DER data.

## Usage

Add a link/button anywhere in your Blade views:

```blade
<a href="{{ route('nepal-payment.initiate', ['gateway' => 'esewa']) }}?amount=100">
    Pay with eSewa
</a>
```

Supported gateway values: `esewa`, `khalti`, `nepal_payment`.

The payment button label defaults to `Pay Now`. Customize it with the `btn-name` component attribute:

```blade
<x-nepal-payment::buttons :amount="100" btn-name="Continue to checkout" />
```

### Custom success/failure redirects

By default, users land on this package's built-in success/failure views. To redirect to your own pages instead, pass `success_url` and `failure_url`. The URLs receive `status` and `transaction_uuid` query parameters, preserving any query parameters already present:

```blade
<a href="{{ route('nepal-payment.initiate', ['gateway' => 'esewa']) }}?amount=100&success_url={{ urlencode(route('your.success.page')) }}&failure_url={{ urlencode(route('your.failure.page')) }}">
    Pay with eSewa
</a>
```

Optionally pass `callback_url` to receive a server-to-server POST when the transaction reaches a verified success or failure state. The JSON body contains `transaction_uuid`, `gateway`, `status`, `success`, `amount`, `metadata`, and `message`. Failed deliveries are logged and can be retried by checking the transaction status again. The package's gateway-return callback endpoint is available at `route('nepal-payment.callback', ['gateway' => 'esewa'])`; register that URL with gateways that require a callback URL.

### Passing metadata

```blade
<a href="{{ route('nepal-payment.initiate', ['gateway' => 'esewa']) }}?amount=100&metadata[order_id]=123">
    Pay with eSewa
</a>
```

### Listening to events

```php
use NepalPay\Gateway\Events\PaymentSucceeded;

Event::listen(PaymentSucceeded::class, function ($event) {
    $transaction = $event->transaction; // NepalPay\Gateway\Models\PaymentTransaction
    // update your own Order model, send confirmation email, etc.
});
```

Available events: `PaymentInitiated`, `PaymentSucceeded`, `PaymentFailed`.

## Gateway status

| Gateway | Status |
|---|---|
| eSewa | ✅ Fully working (public sandbox credentials included) |
| Khalti | ✅ Fully working (requires your own sandbox key) |
| Nepal Payment (OnePG) | ✅ Code complete and verified against real sandbox API — requires your own merchant credentials from nepalpayment.com, and your callback URL must be registered with their support team |

## Database

Creates a `payment_transactions` table with: `gateway`, `transaction_uuid`, `amount`, `status` (`pending`/`success`/`failed`), `metadata` (json), `gateway_response` (json), `success_url`, `failure_url`, `callback_url`, and `callback_delivered_at`.

## License

MIT