<?php

namespace NepalPay\Gateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class GatewaySetting extends Model
{
    protected $fillable = [
        'gateway',
        'is_enabled',
        'mode',
        'dev_credentials',
        'live_credentials',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
 * Get decrypted credentials for a specific mode.
 */
public function getCredentialsForMode(string $mode): array
{
    $column = $mode === 'live' ? 'live_credentials' : 'dev_credentials';
    $raw = $this->{$column};

    if (!$raw) {
        return [];
    }

    try {
        return json_decode(Crypt::decryptString($raw), true) ?? [];
    } catch (\Exception $e) {
        return [];
    }
}

    /**
     * Get decrypted credentials for the current mode.
     */
    public function getCredentials(): array
    {
        $column = $this->mode === 'live' ? 'live_credentials' : 'dev_credentials';
        $raw = $this->{$column};

        if (!$raw) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($raw), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Set encrypted credentials for a given mode.
     */
    public function setCredentials(string $mode, array $credentials): void
    {
        $column = $mode === 'live' ? 'live_credentials' : 'dev_credentials';
        $this->{$column} = Crypt::encryptString(json_encode($credentials));
    }
}