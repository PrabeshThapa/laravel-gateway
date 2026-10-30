<?php

namespace NepalPay\Gateway\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use NepalPay\Gateway\Contracts\PaymentGatewayContract;
use NepalPay\Gateway\DTO\PaymentInitiation;
use NepalPay\Gateway\DTO\PaymentResult;
use NepalPay\Gateway\Models\PaymentTransaction;
use RuntimeException;

class FonepayDriver implements PaymentGatewayContract
{
    protected array $config;

    public function __construct()
    {
        $setting = \NepalPay\Gateway\Models\GatewaySetting::where('gateway', 'fonepay')->first();
        $mode = $setting?->mode ?? config('nepal-payment.mode', 'dev');
        $dbCredentials = $setting?->getCredentials() ?? [];
        $fallbackConfig = config("nepal-payment.gateways.fonepay.{$mode}", []);
        $this->config = array_merge($fallbackConfig, array_filter($dbCredentials));
    }

    public function initiate(array $payload): PaymentInitiation
    {
        if (!$this->isConfigured()) {
            return new PaymentInitiation(
                transactionUuid: (string) Str::uuid(),
                view: 'nepal-payment::failure',
                viewData: ['message' => 'Fonepay is not configured yet. Please add credentials in the admin dashboard.'],
            );
        }

        $amount = (float) $payload['amount'];
        $referenceLabel = 'NP' . now()->format('YmdHis') . Str::upper(Str::random(4));

        $transaction = PaymentTransaction::create([
            'gateway' => 'fonepay',
            'transaction_uuid' => $referenceLabel,
            'amount' => $amount,
            'status' => 'pending',
            'metadata' => $payload['metadata'] ?? [],
            'success_url' => $payload['success_url'] ?? null,
            'failure_url' => $payload['failure_url'] ?? null,
        ]);

        event(new \NepalPay\Gateway\Events\PaymentInitiated($transaction));

        try {
            $response = $this->request('post', '/generate-intent-qr', [
                'amount' => $amount,
                'billId' => $referenceLabel,
                'terminalId' => $this->config['terminal_id'],
                'paymentMode' => 'QR',
                'referenceLabel' => $referenceLabel,
                'qrType' => 'INTENT_QR',
            ]);
        } catch (\Throwable $e) {
            $transaction->update(['status' => 'failed']);

            return new PaymentInitiation(
                transactionUuid: $referenceLabel,
                view: 'nepal-payment::failure',
                viewData: ['message' => 'Could not connect to Fonepay: ' . $e->getMessage()],
            );
        }

        $body = $response->json();

        if (!$response->successful() || empty($body['qrString'])) {
            $transaction->update([
                'status' => 'failed',
                'gateway_response' => $body,
            ]);

            return new PaymentInitiation(
                transactionUuid: $referenceLabel,
                view: 'nepal-payment::failure',
                viewData: ['message' => 'Could not generate the Fonepay QR code.'],
            );
        }

        $transaction->update(['gateway_response' => $body]);

        return new PaymentInitiation(
            transactionUuid: $referenceLabel,
            view: 'nepal-payment::fonepay-qr',
            viewData: [
                'qr_string' => $body['qrString'],
                'merchant_name' => $body['qrDisplayName'] ?? $body['terminalName'] ?? 'Fonepay',
                'reference_label' => $referenceLabel,
            ],
        );
    }

    public function handleReturn(Request $request): PaymentResult
    {
        // Fonepay QR has no browser redirect callback - status is confirmed via verify() polling.
        $referenceLabel = $request->query('reference') ?? $request->input('reference');

        if (!$referenceLabel) {
            return new PaymentResult(success: false, status: 'failed', transactionUuid: '', message: 'No reference provided.');
        }

        return $this->verify($referenceLabel);
    }

    public function verify(string $transactionUuid): PaymentResult
    {
        $transaction = PaymentTransaction::where('transaction_uuid', $transactionUuid)->first();

        if (!$this->isConfigured() || !$transaction) {
            return new PaymentResult(success: false, status: 'failed', transactionUuid: $transactionUuid, message: 'Transaction not found or Fonepay not configured.');
        }

        try {
            $response = $this->request('post', '/thirdPartyDynamicQrGetStatus', [
                'terminalId' => $this->config['terminal_id'],
                'referenceLabel' => $transactionUuid,
            ]);
        } catch (\Throwable $e) {
            return new PaymentResult(success: false, status: 'pending', transactionUuid: $transactionUuid, message: $e->getMessage());
        }

        $body = $response->json();
        $paymentStatus = strtolower((string) ($body['paymentStatus'] ?? 'pending'));

        $status = match (true) {
            $paymentStatus === 'success' => 'success',
            in_array($paymentStatus, ['failed', 'cancelled', 'canceled']) => 'failed',
            default => 'pending',
        };

        if ($status !== 'pending' && $transaction->status === 'pending') {
            $transaction->update([
                'status' => $status,
                'gateway_response' => $body,
            ]);

            event(
                $status === 'success'
                    ? new \NepalPay\Gateway\Events\PaymentSucceeded($transaction)
                    : new \NepalPay\Gateway\Events\PaymentFailed($transaction)
            );
        }

        return new PaymentResult(
            success: $status === 'success',
            status: $status,
            transactionUuid: $transactionUuid,
            amount: (float) $transaction->amount,
            raw: $body ?? [],
        );
    }

    protected function request(string $method, string $path, array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $token = $this->accessToken();

        return Http::withHeaders([
            'Authorization' => $token,
            'signature' => $this->sign($body),
        ])->withBody($body, 'application/json')->send($method, rtrim($this->config['base_url'], '/') . $path);
    }

    protected function accessToken(): string
    {
        $cacheKey = 'fonepay_access_token_' . md5($this->config['username'] ?? '');

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $payload = json_encode([
                'username' => $this->config['username'],
                'password' => $this->config['password'],
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->config['username'] . ':' . $this->config['password']),
                'signature' => $this->sign($payload),
            ])->withBody($payload, 'application/json')->post(rtrim($this->config['base_url'], '/') . '/login');

            \Illuminate\Support\Facades\Log::info('Fonepay authentication request', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if (!$response->successful() || !$response->json('accessToken')) {
                throw new RuntimeException('Fonepay authentication failed.');
            }

            return $response->json('accessToken');
        });
    }

    protected function sign(string $payload): string
    {
        $privateKey = base64_decode((string) $this->config['private_key'], true);

        if ($privateKey === false) {
            throw new RuntimeException('The Fonepay private key must be base64-encoded PKCS#8 data.');
        }

        $pem = "-----BEGIN PRIVATE KEY-----\n" . chunk_split(base64_encode($privateKey), 64, "\n") . "-----END PRIVATE KEY-----\n";

        if (!openssl_sign($payload, $signature, $pem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the Fonepay request.');
        }

        return base64_encode($signature);
    }

    protected function isConfigured(): bool
    {
        return (bool) (
            ($this->config['base_url'] ?? null)
            && ($this->config['username'] ?? null)
            && ($this->config['password'] ?? null)
            && ($this->config['private_key'] ?? null)
            && ($this->config['terminal_id'] ?? null)
        );
    }
}
