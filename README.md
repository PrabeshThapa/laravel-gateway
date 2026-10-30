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

This publishes the config file, publishes and runs the migration, and prints next steps.

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
```

Switch to `PAYMENT_MODE=live` and fill in the corresponding `_LIVE_` keys when going to production.

## Usage

Add a link/button anywhere in your Blade views:

```blade
<a href="{{ route('nepal-payment.initiate', ['gateway' => 'esewa']) }}?amount=100">
    Pay with eSewa
</a>
```

Supported gateway values: `esewa`, `khalti`, `nepal_payment`.

### Custom success/failure redirects

By default, users land on this package's built-in success/failure views. To redirect to your own pages instead, pass `success_url` and `failure_url`:

```blade
<a href="{{ route('nepal-payment.initiate', ['gateway' => 'esewa']) }}?amount=100&success_url={{ urlencode(route('your.success.page')) }}&failure_url={{ urlencode(route('your.failure.page')) }}">
    Pay with eSewa
</a>
```

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

Creates a `payment_transactions` table with: `gateway`, `transaction_uuid`, `amount`, `status` (`pending`/`success`/`failed`), `metadata` (json), `gateway_response` (json), `success_url`, `failure_url`.

## License

MIT