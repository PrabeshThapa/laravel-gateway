<div style="display: flex; flex-wrap: wrap; gap: 12px;">
@php
    $colors = [
        'esewa' => '#60bb46',
        'khalti' => '#5c2d91',
        'nepal_payment' => '#1f6feb',
        'fonepay' => '#d6455e',
    ];
@endphp
@forelse ($enabledGateways as $gateway)
    @php
        $url = route('nepal-payment.initiate', ['gateway' => $gateway['key']]) . '?amount=' . urlencode($amount);
        if ($successUrl) { $url .= '&success_url=' . urlencode($successUrl); }
        if ($failureUrl) { $url .= '&failure_url=' . urlencode($failureUrl); }
        $color = $colors[$gateway['key']] ?? '#334155';
    @endphp
    <a href="{{ $url }}" style="display:inline-block;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;color:#fff;background:{{ $color }};font-family:sans-serif;">{{ $gateway['label'] }}</a>
@empty
    <p style="color:#94a3b8;font-family:sans-serif;font-size:14px;">No payment methods are currently available.</p>
@endforelse
</div>