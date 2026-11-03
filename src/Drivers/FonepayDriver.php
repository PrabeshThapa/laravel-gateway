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
    private const LOGIN_PATH = '/api/merchant/third-party/v2/login';
    private const THIRD_PARTY_API_PATH = '/api/merchant/third-party/v2';

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
            'callback_url' => $payload['callback_url'] ?? null,
        ]);

        event(new \NepalPay\Gateway\Events\PaymentInitiated($transaction));

        try {
            $response = $this->request('post', self::THIRD_PARTY_API_PATH . '/generate-intent-qr', [
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
            \Illuminate\Support\Facades\Log::warning('Fonepay QR generation was rejected', [
                'url' => $this->apiUrl(self::THIRD_PARTY_API_PATH . '/generate-intent-qr'),
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'redirect_location' => $this->safeRedirectLocation($response->header('Location')),
                'html_title' => $this->responseHtmlTitle($response->body()),
                'provider_response' => is_array($body)
                    ? $this->redactProviderResponse($body)
                    : $this->redactProviderText($response->body()),
            ]);

            $transaction->update([
                'status' => 'failed',
                'gateway_response' => $body,
            ]);

            return new PaymentInitiation(
                transactionUuid: $referenceLabel,
                view: 'nepal-payment::failure',
                viewData: ['message' => "Could not generate the Fonepay QR code (HTTP {$response->status()})."],
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
            $response = $this->request('post', self::THIRD_PARTY_API_PATH . '/thirdPartyDynamicQrGetStatus', [
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
        $headers = [
            'signature' => $this->sign($body),
        ];
        $response = $this->sendSignedRequest($method, $this->apiUrl($path), $body, [
            ...$headers,
            'Authorization' => $this->accessToken(),
        ]);

        if ($response->status() === 401) {
            Cache::forget($this->accessTokenCacheKey());
            $response = $this->sendSignedRequest($method, $this->apiUrl($path), $body, [
                ...$headers,
                'Authorization' => $this->accessToken(),
            ]);
        }

        return $response;
    }

    protected function accessToken(): string
    {
        $cacheKey = $this->accessTokenCacheKey();

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $payload = json_encode([
                'username' => $this->config['username'],
                'password' => $this->config['password'],
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $response = Http::timeout(15)->withoutRedirecting()->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->config['username'] . ':' . $this->config['password']),
                'Content-Type' => 'application/json',
                'signature' => $this->sign($payload),
            ])->withBody($payload, 'application/json')->post($this->apiUrl(self::LOGIN_PATH));

            \Illuminate\Support\Facades\Log::info('Fonepay authentication request', [
                'url' => $this->apiUrl(self::LOGIN_PATH),
                'status' => $response->status(),
                'successful' => $response->successful(),
                'redirect_location' => $this->safeRedirectLocation($response->header('Location')),
                'content_type' => $response->header('Content-Type'),
                'username_configured' => (string) ($this->config['username'] ?? '') !== '',
                'password_configured' => (string) ($this->config['password'] ?? '') !== '',
            ]);

            $responseBody = $response->json();
            $token = is_array($responseBody) ? ($responseBody['accessToken'] ?? null) : null;

            if (!$response->successful() || !is_string($token) || $token === '') {
                \Illuminate\Support\Facades\Log::warning('Fonepay authentication was rejected', [
                    'status' => $response->status(),
                    'redirect_location' => $this->safeRedirectLocation($response->header('Location')),
                    'provider_response' => is_array($responseBody)
                        ? $this->redactProviderResponse($responseBody)
                        : '[non-JSON response omitted]',
                ]);

                throw new RuntimeException(
                    "Fonepay authentication failed (HTTP {$response->status()}). Check the Fonepay API URL and merchant API credentials."
                );
            }

            return $token;
        });
    }

    protected function redactProviderResponse(array $response): array
    {
        $secrets = array_filter([
            (string) ($this->config['username'] ?? ''),
            (string) ($this->config['password'] ?? ''),
            (string) ($this->config['private_key'] ?? ''),
        ]);

        $redact = function (mixed $value, ?string $field = null) use (&$redact, $secrets): mixed {
            if ($field !== null && preg_match('/password|token|secret|authorization|credential|private.?key|api.?key|jwt|qr(string|message)?|websocket/i', $field)) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                $redacted = [];
                foreach ($value as $key => $item) {
                    $redacted[$key] = $redact($item, (string) $key);
                }

                return $redacted;
            }

            if (is_string($value)) {
                return str_replace($secrets, '[REDACTED]', $value);
            }

            return $value;
        };

        return $redact($response);
    }

    protected function redactProviderText(string $response): string
    {
        $response = trim($response);

        if ($response === '') {
            return '[empty response body]';
        }

        if (str_starts_with($response, '000201') || strlen($response) > 500) {
            return '[non-JSON response omitted; ' . strlen($response) . ' bytes]';
        }

        $secrets = array_filter([
            (string) ($this->config['username'] ?? ''),
            (string) ($this->config['password'] ?? ''),
            (string) ($this->config['private_key'] ?? ''),
        ]);

        return str_replace($secrets, '[REDACTED]', substr($response, 0, 500));
    }

    protected function safeRedirectLocation(?string $location): ?string
    {
        if (!$location) {
            return null;
        }

        $parts = parse_url($location);
        if ($parts === false) {
            return '[invalid redirect URL]';
        }

        return ($parts['scheme'] ?? '') . '://'
            . ($parts['host'] ?? '')
            . ($parts['port'] ?? null ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '');
    }

    protected function responseHtmlTitle(string $body): ?string
    {
        if (!preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            return null;
        }

        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $secrets = array_filter([
            (string) ($this->config['username'] ?? ''),
            (string) ($this->config['password'] ?? ''),
            (string) ($this->config['private_key'] ?? ''),
        ]);

        return str_replace($secrets, '[REDACTED]', substr($title, 0, 200));
    }

    protected function sendSignedRequest(string $method, string $url, string $body, array $headers)
    {
        return Http::timeout(15)
            ->withoutRedirecting()
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->send($method, $url);
    }

    protected function apiUrl(string $path): string
    {
        $baseUrl = rtrim(trim((string) ($this->config['base_url'] ?? '')), '/');
        $legacyApiPath = self::THIRD_PARTY_API_PATH;

        if (str_ends_with($baseUrl, $legacyApiPath)) {
            $baseUrl = substr($baseUrl, 0, -strlen($legacyApiPath));
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    protected function accessTokenCacheKey(): string
    {
        $identity = implode("\0", [
            rtrim((string) ($this->config['base_url'] ?? ''), '/'),
            (string) ($this->config['username'] ?? ''),
            (string) ($this->config['password'] ?? ''),
        ]);

        return 'fonepay_access_token_' . hash('sha256', $identity);
    }

    protected function sign(string $payload): string
    {
        $privateKey = trim((string) ($this->config['private_key'] ?? ''));

        if ($privateKey === '') {
            throw new RuntimeException('The Fonepay private key is not configured.');
        }

        if (!str_contains($privateKey, '-----BEGIN')) {
            $der = base64_decode($privateKey, true);

            if ($der !== false) {
                $privateKey = "-----BEGIN PRIVATE KEY-----\n"
                    . chunk_split(base64_encode($der), 64, "\n")
                    . "-----END PRIVATE KEY-----\n";
            } else {
                $path = str_starts_with($privateKey, DIRECTORY_SEPARATOR)
                    || preg_match('/^[A-Za-z]:[\\\\\/]/', $privateKey) === 1
                    ? $privateKey
                    : base_path($privateKey);

                if (!is_file($path) || !is_readable($path)) {
                    throw new RuntimeException('The configured Fonepay private key is neither valid base64 PKCS#8 data nor a readable key file.');
                }

                $keyContents = file_get_contents($path);
                if ($keyContents === false) {
                    throw new RuntimeException('Could not read the configured Fonepay private key file.');
                }

                $privateKey = $keyContents;
            }
        }

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new RuntimeException('The configured Fonepay private key could not be loaded.');
        }

        if (!openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
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
