<?php

namespace NepalPay\Gateway\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'gateway',
        'transaction_uuid',
        'amount',
        'status',
        'metadata',
        'gateway_response',
        'success_url',
        'failure_url',
        'callback_url',
        'callback_delivered_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'gateway_response' => 'array',
        'amount' => 'decimal:2',
        'callback_delivered_at' => 'datetime',
    ];
}