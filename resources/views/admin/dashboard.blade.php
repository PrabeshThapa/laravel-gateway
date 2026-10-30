<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Gateway Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 32px 20px;
            color: #0f172a;
        }
        .container { max-width: 760px; margin: 0 auto; }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .logo {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: 15px;
        }
        h1 { font-size: 18px; margin: 0; }
        .subtitle { font-size: 13px; color: #64748b; margin: 2px 0 0; }
        .logout-btn {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            color: #64748b;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .logout-btn:hover { border-color: #cbd5e1; color: #334155; }
        .status-banner {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 24px;
        }
        .grid { display: grid; gap: 14px; }
        .gateway-card {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: border-color 0.15s;
        }
        .gateway-card:hover { border-color: #c7d2fe; }
        .gateway-info { display: flex; align-items: center; gap: 16px; }
        .gateway-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            font-size: 15px;
            color: #fff;
            flex-shrink: 0;
        }
        .icon-esewa { background: #60bb46; }
        .icon-khalti { background: #5c2d91; }
        .icon-nepal_payment { background: #1f6feb; }
        .icon-fonepay { background: #d6455e; }
        .gateway-name {
            font-weight: 700;
            font-size: 15px;
            text-transform: capitalize;
            margin-bottom: 2px;
        }
        .gateway-meta { font-size: 12px; color: #94a3b8; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .badge-dev { background: #fef3c7; color: #92400e; }
        .badge-live { background: #dbeafe; color: #1e40af; }
        .actions { display: flex; align-items: center; gap: 12px; }
        .edit-link {
            color: #4f46e5;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 8px;
            transition: background 0.15s;
        }
        .edit-link:hover { background: #eef2ff; }
        .toggle-switch {
            position: relative;
            width: 46px;
            height: 26px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .toggle-switch.on { background: #22c55e; }
        .toggle-switch.off { background: #cbd5e1; }
        .toggle-knob {
            position: absolute;
            top: 3px;
            width: 20px; height: 20px;
            background: #fff;
            border-radius: 50%;
            transition: left 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .toggle-switch.on .toggle-knob { left: 23px; }
        .toggle-switch.off .toggle-knob { left: 3px; }
        form.inline { margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <div class="brand">
                <div class="logo">NP</div>
                <div>
                    <h1>Payment Gateways</h1>
                    <p class="subtitle">Enable methods and manage credentials</p>
                </div>
            </div>
            <form method="POST" action="{{ route('nepal-payment.admin.logout') }}" class="inline">
                @csrf
                <button type="submit" class="logout-btn">Log out</button>
            </form>
        </div>

        @if (session('status'))
            <div class="status-banner">{{ session('status') }}</div>
        @endif

        <div class="grid">
            @foreach ($settings as $gateway => $setting)
                <div class="gateway-card">
                    <div class="gateway-info">
                        <div class="gateway-icon icon-{{ $gateway }}">{{ strtoupper(substr(str_replace('_', ' ', $gateway), 0, 2)) }}</div>
                        <div>
                            <div class="gateway-name">{{ str_replace('_', ' ', $gateway) }}</div>
                            <span class="badge badge-{{ $setting->mode }}">{{ $setting->mode }}</span>
                        </div>
                    </div>
                    <div class="actions">
                        <a class="edit-link" href="{{ route('nepal-payment.admin.edit', $gateway) }}">Edit credentials</a>
                        <form method="POST" action="{{ route('nepal-payment.admin.toggle', $gateway) }}" class="inline">
                            @csrf
                            <button type="submit" class="toggle-switch {{ $setting->is_enabled ? 'on' : 'off' }}" title="{{ $setting->is_enabled ? 'Enabled' : 'Disabled' }}">
                                <span class="toggle-knob"></span>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>