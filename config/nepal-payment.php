<?php

return [

    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'esewa'),

    'mode' => env('PAYMENT_MODE', 'dev'),

    'admin' => [
        'username' => env('NEPAL_PAYMENT_ADMIN_USER', 'admin'),
        'password_hash' => env('NEPAL_PAYMENT_ADMIN_PASSWORD_HASH'),
        'route_prefix' => env('NEPAL_PAYMENT_ADMIN_PREFIX', 'nepal-payment/admin'),
    ],

    'gateways' => [

        'esewa' => [
            'dev' => [
                'merchant_code' => env('ESEWA_DEV_MERCHANT_CODE', 'EPAYTEST'),
                'secret_key'    => env('ESEWA_DEV_SECRET_KEY', '8gBm/:&EnhH.1/q'),
                'form_url'      => 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
                'status_url'    => 'https://rc.esewa.com.np/api/epay/transaction/status/',
            ],
            'live' => [
                'merchant_code' => env('ESEWA_LIVE_MERCHANT_CODE'),
                'secret_key'    => env('ESEWA_LIVE_SECRET_KEY'),
                'form_url'      => 'https://epay.esewa.com.np/api/epay/main/v2/form',
                'status_url'    => 'https://esewa.com.np/api/epay/transaction/status/',
            ],
        ],

        'khalti' => [
            'dev' => [
                'secret_key'   => env('KHALTI_DEV_SECRET_KEY'),
                'initiate_url' => 'https://dev.khalti.com/api/v2/epayment/initiate/',
                'lookup_url'   => 'https://dev.khalti.com/api/v2/epayment/lookup/',
            ],
            'live' => [
                'secret_key'   => env('KHALTI_LIVE_SECRET_KEY'),
                'initiate_url' => 'https://khalti.com/api/v2/epayment/initiate/',
                'lookup_url'   => 'https://khalti.com/api/v2/epayment/lookup/',
            ],
        ],

        'nepal_payment' => [
            'dev' => [
                'merchant_id'   => env('NEPALPAYMENT_DEV_MERCHANT_ID'),
                'merchant_name' => env('NEPALPAYMENT_DEV_MERCHANT_NAME'),
                'api_username'  => env('NEPALPAYMENT_DEV_API_USERNAME'),
                'api_password'  => env('NEPALPAYMENT_DEV_API_PASSWORD'),
                'api_secret'    => env('NEPALPAYMENT_DEV_API_SECRET'),
                'api_url'       => 'https://apisandbox.nepalpayment.com/',
                'gateway_url'   => 'https://gatewaysandbox.nepalpayment.com/',
            ],
            'live' => [
                'merchant_id'   => env('NEPALPAYMENT_LIVE_MERCHANT_ID'),
                'merchant_name' => env('NEPALPAYMENT_LIVE_MERCHANT_NAME'),
                'api_username'  => env('NEPALPAYMENT_LIVE_API_USERNAME'),
                'api_password'  => env('NEPALPAYMENT_LIVE_API_PASSWORD'),
                'api_secret'    => env('NEPALPAYMENT_LIVE_API_SECRET'),
                'api_url'       => env('NEPALPAYMENT_LIVE_API_URL'),
                'gateway_url'   => env('NEPALPAYMENT_LIVE_GATEWAY_URL'),
            ],
        ],
                'fonepay' => [
            'dev' => [
                'base_url' => env('FONEPAY_DEV_BASE_URL', 'https://uat-new-merchant-api.fonepay.com/api/merchant/third-party/v2'),
                'username' => env('FONEPAY_DEV_USERNAME'),
                'password' => env('FONEPAY_DEV_PASSWORD'),
                'private_key' => env('FONEPAY_DEV_PRIVATE_KEY'),
                'terminal_id' => env('FONEPAY_DEV_TERMINAL_ID'),
            ],
            'live' => [
                'base_url' => env('FONEPAY_LIVE_BASE_URL'),
                'username' => env('FONEPAY_LIVE_USERNAME'),
                'password' => env('FONEPAY_LIVE_PASSWORD'),
                'private_key' => env('FONEPAY_LIVE_PRIVATE_KEY'),
                'terminal_id' => env('FONEPAY_LIVE_TERMINAL_ID'),
            ],
        ],

    ],

    'route_prefix' => env('PAYMENT_ROUTE_PREFIX', 'payment'),

];