<?php

namespace NepalPay\Gateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NepalPay\Gateway\Events\PaymentFailed;
use NepalPay\Gateway\Events\PaymentSucceeded;
use NepalPay\Gateway\Exceptions\DriverNotImplementedException;
use NepalPay\Gateway\Facades\NepalPayment;
use NepalPay\Gateway\Models\PaymentTransaction;

class PaymentController extends Controller
{
    public function initiate(Request $request, string $gateway)
    {
        $request->validate([
            'success_url' => ['nullable', 'url'],
            'failure_url' => ['nullable', 'url'],
            'callback_url' => ['nullable', 'url'],
        ]);

        try {
            $driver = NepalPayment::driver($gateway);

            $initiation = $driver->initiate([
                'amount' => $request->query('amount', $request->input('amount')),
                'metadata' => $request->input('metadata', []),
                'success_url' => $request->input('success_url'),
                'failure_url' => $request->input('failure_url'),
                'callback_url' => $request->input('callback_url'),
            ]);

            return view($initiation->view, $initiation->viewData);
        } catch (DriverNotImplementedException $e) {
            return response()->view('nepal-payment::failure', [
                'message' => $e->getMessage(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->view('nepal-payment::failure', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function success(Request $request, string $gateway)
    {
        return $this->handleGatewayReturn($request, $gateway);
    }

    public function failure(Request $request, string $gateway)
    {
        return $this->handleGatewayReturn($request, $gateway);
    }

    public function callback(Request $request, string $gateway)
    {
        return $this->handleGatewayReturn($request, $gateway);
    }

    public function status(Request $request, string $gateway)
    {
        $reference = $request->query('reference');
        if (!$reference) {
            return response()->json(['status' => 'failed', 'message' => 'Missing reference'], 400);
        }

        $driver = NepalPayment::driver($gateway);
        $result = $driver->verify($reference);

        $transaction = PaymentTransaction::where('transaction_uuid', $reference)->first();
        $this->syncTransaction($transaction, $result);
        $this->deliverCallback($transaction, $result);

        $redirectUrl = null;
        if ($result->success) {
            $redirectUrl = $transaction?->success_url
                ? $this->withStatus($transaction->success_url, $result, $transaction->transaction_uuid)
                : route('nepal-payment.success', ['gateway' => $gateway, 'reference' => $reference]);
        } elseif ($result->status === 'failed') {
            $redirectUrl = $transaction?->failure_url
                ? $this->withStatus($transaction->failure_url, $result, $transaction->transaction_uuid)
                : route('nepal-payment.failure', ['gateway' => $gateway, 'reference' => $reference]);
        }

        return response()->json([
            'status' => $result->status,
            'success' => $result->success,
            'redirect_url' => $redirectUrl,
        ]);
    }

    protected function handleGatewayReturn(Request $request, string $gateway)
    {
        $driver = NepalPayment::driver($gateway);
        $reference = $request->query('reference');
        $result = $reference
            ? $driver->verify($reference)
            : $driver->handleReturn($request);
        $transaction = PaymentTransaction::where(
            'transaction_uuid',
            $reference ?? $result->transactionUuid
        )->first();

        $this->syncTransaction($transaction, $result);
        $this->deliverCallback($transaction, $result);

        if ($result->status === 'success') {
            if ($transaction?->success_url) {
                return redirect()->away($this->withStatus(
                    $transaction->success_url,
                    $result,
                    $transaction->transaction_uuid
                ));
            }

            return view('nepal-payment::success', ['result' => $result]);
        }

        if ($result->status === 'failed') {
            if ($transaction?->failure_url) {
                return redirect()->away($this->withStatus(
                    $transaction->failure_url,
                    $result,
                    $transaction->transaction_uuid
                ));
            }

            return view('nepal-payment::failure', [
                'message' => $result->message ?? 'Payment failed.',
            ]);
        }

        return response()->view('nepal-payment::failure', [
            'message' => $result->message ?? 'Payment status is pending.',
        ], 202);
    }

    protected function syncTransaction(?PaymentTransaction $transaction, \NepalPay\Gateway\DTO\PaymentResult $result): void
    {
        if (!$transaction || !in_array($result->status, ['success', 'failed'], true)) {
            return;
        }

        if ($transaction->status === 'pending') {
            $transaction->update([
                'status' => $result->status,
                'gateway_response' => $result->raw,
            ]);
            $transaction->refresh();

            event(
                $result->success
                    ? new PaymentSucceeded($transaction)
                    : new PaymentFailed($transaction)
            );
        }
    }

    protected function deliverCallback(?PaymentTransaction $transaction, \NepalPay\Gateway\DTO\PaymentResult $result): void
    {
        if (
            !$transaction
            || !$transaction->callback_url
            || $transaction->callback_delivered_at
            || !in_array($result->status, ['success', 'failed'], true)
        ) {
            return;
        }

        try {
            $response = Http::timeout(10)->post($transaction->callback_url, [
                'transaction_uuid' => $transaction->transaction_uuid,
                'gateway' => $transaction->gateway,
                'status' => $result->status,
                'success' => $result->success,
                'amount' => $result->amount ?? (float) $transaction->amount,
                'metadata' => $transaction->metadata,
                'message' => $result->message,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            Log::error('Payment callback delivery failed.', [
                'transaction_uuid' => $transaction->transaction_uuid,
                'callback_url' => $transaction->callback_url,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (!$response->successful()) {
            Log::error('Payment callback endpoint returned an unsuccessful response.', [
                'transaction_uuid' => $transaction->transaction_uuid,
                'callback_url' => $transaction->callback_url,
                'status' => $response->status(),
            ]);

            return;
        }

        $transaction->update(['callback_delivered_at' => now()]);
    }

    protected function withStatus(
        string $url,
        \NepalPay\Gateway\DTO\PaymentResult $result,
        ?string $transactionUuid = null
    ): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $query['status'] = $result->status;
        $query['transaction_uuid'] = $transactionUuid ?? $result->transactionUuid;

        $baseUrl = strtok($url, '?#');
        $queryString = http_build_query($query);
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $baseUrl . ($queryString !== '' ? '?' . $queryString : '') . $fragment;
    }
}