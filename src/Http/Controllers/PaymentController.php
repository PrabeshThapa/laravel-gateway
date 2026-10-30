<?php

namespace NepalPay\Gateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use NepalPay\Gateway\Exceptions\DriverNotImplementedException;
use NepalPay\Gateway\Facades\NepalPayment;

class PaymentController extends Controller
{
    public function initiate(Request $request, string $gateway)
    {
        try {
            $driver = NepalPayment::driver($gateway);

            $initiation = $driver->initiate([
                'amount' => $request->query('amount', $request->input('amount')),
                'metadata' => $request->input('metadata', []),
                'success_url' => $request->query('success_url'),
                'failure_url' => $request->query('failure_url'),
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
        $driver = NepalPayment::driver($gateway);
        $result = $driver->handleReturn($request);

        $transaction = \NepalPay\Gateway\Models\PaymentTransaction::where(
            'transaction_uuid',
            $result->transactionUuid
        )->first();

        if ($transaction && $transaction->success_url) {
            return redirect()->away($transaction->success_url . '?status=' . $result->status);
        }

        return view('nepal-payment::success', [
            'result' => $result,
        ]);
    }

    public function failure(Request $request, string $gateway)
    {
        $driver = NepalPayment::driver($gateway);
        $result = $driver->handleReturn($request);

        $transaction = \NepalPay\Gateway\Models\PaymentTransaction::where(
            'transaction_uuid',
            $result->transactionUuid
        )->first();

        if ($transaction && $transaction->failure_url) {
            return redirect()->away($transaction->failure_url . '?status=' . $result->status);
        }

        return view('nepal-payment::failure', [
            'message' => $result->message ?? 'Payment failed.',
        ]);
    }
    public function status(Request $request, string $gateway)
    {
        $reference = $request->query('reference');
        if (!$reference) {
            return response()->json(['status' => 'failed', 'message' => 'Missing reference'], 400);
        }

        $driver = NepalPayment::driver($gateway);
        $result = $driver->verify($reference);

        $transaction = \NepalPay\Gateway\Models\PaymentTransaction::where('transaction_uuid', $reference)->first();

        $redirectUrl = null;
        if ($result->success) {
            $redirectUrl = $transaction?->success_url
                ? $transaction->success_url . '?status=success'
                : route('nepal-payment.success', ['gateway' => $gateway, 'reference' => $reference]);
        } elseif ($result->status === 'failed') {
            $redirectUrl = $transaction?->failure_url
                ? $transaction->failure_url . '?status=failed'
                : route('nepal-payment.failure', ['gateway' => $gateway, 'reference' => $reference]);
        }

    return response()->json([
            'status' => $result->status,
            'success' => $result->success,
            'redirect_url' => $redirectUrl,
        ]);
    }
}