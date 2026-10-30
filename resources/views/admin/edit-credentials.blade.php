<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit {{ ucfirst($gateway) }} Credentials</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 32px 20px;
            color: #0f172a;
        }

        .container {
            max-width: 620px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .back-link:hover {
            color: #334155;
        }

        .panel {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
        }

        h1 {
            font-size: 20px;
            text-transform: capitalize;
            margin: 0 0 4px;
        }

        .panel-subtitle {
            color: #64748b;
            font-size: 13px;
            margin: 0 0 28px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            margin-top: 16px;
        }

        input,
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.15s;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #6366f1;
        }

        .mode-select {
            margin-bottom: 8px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 32px;
            margin-bottom: 4px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
            font-weight: 700;
            font-size: 14px;
        }

        .section-badge {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .section-badge.dev {
            background: #fef3c7;
            color: #92400e;
        }

        .section-badge.live {
            background: #dbeafe;
            color: #1e40af;
        }

        button {
            margin-top: 28px;
            width: 100%;
            padding: 12px;
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.15s;
        }

        button:hover {
            background: #4338ca;
        }
    </style>
</head>

<body>
    <div class="container">
        <a class="back-link" href="{{ route('nepal-payment.admin.dashboard') }}">&larr; Back to dashboard</a>

        <div class="panel">
            <h1>{{ str_replace('_', ' ', $gateway) }}</h1>
            <p class="panel-subtitle">Update credentials and choose which mode is active</p>

            <form method="POST" action="{{ route('nepal-payment.admin.update', $gateway) }}">
                @csrf

                <label>Active mode</label>
                <select name="mode" class="mode-select">
                    <option value="dev" {{ $setting->mode === 'dev' ? 'selected' : '' }}>Dev / Sandbox</option>
                    <option value="live" {{ $setting->mode === 'live' ? 'selected' : '' }}>Live / Production</option>
                </select>

                <div class="section-title">
                    Dev credentials <span class="section-badge dev">sandbox</span>
                </div>
                @if ($gateway === 'esewa')
                    <label>Merchant Code</label>
                    <input type="text" name="dev[merchant_code]" value="{{ $devCredentials['merchant_code'] ?? '' }}"
                        placeholder="EPAYTEST">
                    <label>Secret Key</label>
                    <input type="text" name="dev[secret_key]" value="{{ $devCredentials['secret_key'] ?? '' }}">
                @elseif ($gateway === 'khalti')
                    <label>Secret Key</label>
                    <input type="text" name="dev[secret_key]" value="{{ $devCredentials['secret_key'] ?? '' }}">
                @elseif ($gateway === 'nepal_payment')
                    <label>Merchant ID</label>
                    <input type="text" name="dev[merchant_id]" value="{{ $devCredentials['merchant_id'] ?? '' }}">
                    <label>Merchant Name</label>
                    <input type="text" name="dev[merchant_name]"
                        value="{{ $devCredentials['merchant_name'] ?? '' }}">
                    <label>API Username</label>
                    <input type="text" name="dev[api_username]" value="{{ $devCredentials['api_username'] ?? '' }}">
                    <label>API Password</label>
                    <input type="text" name="dev[api_password]" value="{{ $devCredentials['api_password'] ?? '' }}">
                    <label>API Secret</label>
                    <input type="text" name="dev[api_secret]" value="{{ $devCredentials['api_secret'] ?? '' }}">
                @elseif ($gateway === 'fonepay')
                    <label>Base URL</label>
                    <input type="text" name="dev[base_url]"
                        value="{{ $devCredentials['base_url'] ?? 'https://uat-new-merchant-api.fonepay.com/api/merchant/third-party/v2' }}">
                    <label>Username</label>
                    <input type="text" name="dev[username]" value="{{ $devCredentials['username'] ?? '' }}">
                    <label>Password</label>
                    <input type="password" name="dev[password]" value="{{ $devCredentials['password'] ?? '' }}">
                    <label>Private Key (base64-encoded PKCS#8)</label>
                    <input type="text" name="dev[private_key]" value="{{ $devCredentials['private_key'] ?? '' }}">
                    <label>Terminal ID</label>
                    <input type="text" name="dev[terminal_id]" value="{{ $devCredentials['terminal_id'] ?? '' }}">
                @endif

                <div class="section-title">
                    Live credentials <span class="section-badge live">production</span>
                </div>
                @if ($gateway === 'esewa')
                    <label>Merchant Code</label>
                    <input type="text" name="live[merchant_code]"
                        value="{{ $liveCredentials['merchant_code'] ?? '' }}">
                    <label>Secret Key</label>
                    <input type="text" name="live[secret_key]" value="{{ $liveCredentials['secret_key'] ?? '' }}">
                @elseif ($gateway === 'khalti')
                    <label>Secret Key</label>
                    <input type="text" name="live[secret_key]" value="{{ $liveCredentials['secret_key'] ?? '' }}">
                @elseif ($gateway === 'nepal_payment')
                    <label>Merchant ID</label>
                    <input type="text" name="live[merchant_id]" value="{{ $liveCredentials['merchant_id'] ?? '' }}">
                    <label>Merchant Name</label>
                    <input type="text" name="live[merchant_name]"
                        value="{{ $liveCredentials['merchant_name'] ?? '' }}">
                    <label>API Username</label>
                    <input type="text" name="live[api_username]"
                        value="{{ $liveCredentials['api_username'] ?? '' }}">
                    <label>API Password</label>
                    <input type="text" name="live[api_password]"
                        value="{{ $liveCredentials['api_password'] ?? '' }}">
                    <label>API Secret</label>
                    <input type="text" name="live[api_secret]" value="{{ $liveCredentials['api_secret'] ?? '' }}">
                @elseif ($gateway === 'fonepay')
                    <label>Base URL</label>
                    <input type="text" name="live[base_url]" value="{{ $liveCredentials['base_url'] ?? '' }}">
                    <label>Username</label>
                    <input type="text" name="live[username]" value="{{ $liveCredentials['username'] ?? '' }}">
                    <label>Password</label>
                    <input type="password" name="live[password]" value="{{ $liveCredentials['password'] ?? '' }}">
                    <label>Private Key (base64-encoded PKCS#8)</label>
                    <input type="text" name="live[private_key]" value="{{ $liveCredentials['private_key'] ?? '' }}">
                    <label>Terminal ID</label>
                    <input type="text" name="live[terminal_id]" value="{{ $liveCredentials['terminal_id'] ?? '' }}">
                @endif

                <button type="submit">Save credentials</button>
            </form>
        </div>
    </div>
</body>

</html>
