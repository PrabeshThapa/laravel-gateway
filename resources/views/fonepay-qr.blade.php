<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pay with Fonepay</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 40px 20px;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }
        .header { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 16px; }
        .logo-badge {
            background: #d6455e;
            color: #fff;
            font-weight: 800;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 14px;
        }
        h2 { margin: 8px 0 4px; font-size: 18px; }
        .sub { color: #64748b; font-size: 13px; margin: 0 0 20px; }
        .qr-wrapper {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            display: inline-block;
            margin-bottom: 20px;
        }
        .qr-img { display: block; width: 220px; height: 220px; margin: 0 auto; }
        .ref-box {
            background: #f1f5f9;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            color: #475569;
            margin-bottom: 20px;
            word-break: break-all;
        }
        .polling-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            color: #475569;
        }
        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #d6455e;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <span class="logo-badge">Fonepay</span>
        </div>
        <h2>Scan to Pay</h2>
        <p class="sub">Open any banking or digital wallet app that supports Fonepay</p>

        <div class="qr-wrapper">
            <!-- Renders standard QR image directly from the EMVCo qr_string -->
            <img class="qr-img" src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data={{ urlencode($qr_string) }}" alt="Fonepay QR Code">
        </div>

        <div class="ref-box">
            <strong>Ref:</strong> {{ $reference_label }}<br>
            <strong>Merchant:</strong> {{ $merchant_name }}
        </div>

        <div class="polling-status">
            <div class="spinner"></div>
            <span id="status-text">Waiting for payment confirmation...</span>
        </div>
    </div>

    <script>
        const reference = "{{ $reference_label }}";
        const checkUrl = "{{ route('nepal-payment.status', ['gateway' => 'fonepay']) }}?reference=" + encodeURIComponent(reference);

        const pollInterval = setInterval(async () => {
            try {
                const res = await fetch(checkUrl);
                if (res.ok) {
                    const data = await res.json();
                    if (data.status === 'success' && data.redirect_url) {
                        clearInterval(pollInterval);
                        document.getElementById('status-text').innerText = 'Payment received! Redirecting...';
                        window.location.href = data.redirect_url;
                    } else if (data.status === 'failed' && data.redirect_url) {
                        clearInterval(pollInterval);
                        document.getElementById('status-text').innerText = 'Payment failed. Redirecting...';
                        window.location.href = data.redirect_url;
                    }
                }
            } catch (err) {
                console.error("Polling error:", err);
            }
        }, 3000);
    </script>
</body>
</html>