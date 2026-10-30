<?php

namespace NepalPay\Gateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use NepalPay\Gateway\Models\GatewaySetting;

class AdminController extends Controller
{
    protected array $availableGateways = ['esewa', 'khalti', 'nepal_payment', 'fonepay'];

    public function showLogin()
    {
        return view('nepal-payment::admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $configUsername = config('nepal-payment.admin.username');
        $configHash = config('nepal-payment.admin.password_hash');

        if (
            $request->username === $configUsername &&
            $configHash &&
            Hash::check($request->password, $configHash)
        ) {
            $request->session()->regenerate();
            session(['nepal_payment_admin_authenticated' => true]);

            return redirect()->route('nepal-payment.admin.dashboard');
        }


        return back()->withErrors(['username' => 'Invalid credentials.']);
    }

    public function logout()
    {
        session()->forget('nepal_payment_admin_authenticated');

        return redirect()->route('nepal-payment.admin.login');
    }

    public function dashboard()
    {
        $settings = collect($this->availableGateways)->mapWithKeys(function ($gateway) {
            $setting = GatewaySetting::firstOrCreate(
                ['gateway' => $gateway],
                ['is_enabled' => false, 'mode' => 'dev']
            );

            return [$gateway => $setting];
        });

        return view('nepal-payment::admin.dashboard', [
            'settings' => $settings,
        ]);
    }

    public function toggle(Request $request, string $gateway)
    {
        if (!in_array($gateway, $this->availableGateways)) {
            abort(404);
        }

        $setting = GatewaySetting::firstOrCreate(['gateway' => $gateway]);
        $setting->update(['is_enabled' => !$setting->is_enabled]);

        return back()->with('status', ucfirst($gateway) . ' ' . ($setting->is_enabled ? 'enabled' : 'disabled') . '.');
    }

    public function editCredentials(string $gateway)
    {
        if (!in_array($gateway, $this->availableGateways)) {
            abort(404);
        }

        $setting = GatewaySetting::firstOrCreate(['gateway' => $gateway]);

        return view('nepal-payment::admin.edit-credentials', [
            'gateway' => $gateway,
            'setting' => $setting,
            'devCredentials' => $setting->getCredentialsForMode('dev'),
            'liveCredentials' => $setting->getCredentialsForMode('live'),
        ]);
    }

    public function updateCredentials(Request $request, string $gateway)
    {
        if (!in_array($gateway, $this->availableGateways)) {
            abort(404);
        }

        $setting = GatewaySetting::firstOrCreate(['gateway' => $gateway]);

        $devCredentials = array_filter($request->input('dev', []));
        $liveCredentials = array_filter($request->input('live', []));
        $mode = $request->input('mode', 'dev');

        // Only overwrite a credential set if the admin actually filled in at least one field.
        if (!empty($devCredentials)) {
            $setting->setCredentials('dev', $devCredentials);
        }

        if (!empty($liveCredentials)) {
            $setting->setCredentials('live', $liveCredentials);
        }

        $setting->mode = $mode;
        $setting->save();

        return redirect()
            ->route('nepal-payment.admin.dashboard')
            ->with('status', ucfirst($gateway) . ' credentials updated.');
    }
}
