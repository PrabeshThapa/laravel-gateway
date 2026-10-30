<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Gateway Admin</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: #fff;
            padding: 48px 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 380px;
        }
        .logo {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 20px;
            margin: 0 auto 20px;
        }
        h1 {
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            margin: 0 0 4px;
            color: #0f172a;
        }
        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 28px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            margin-bottom: 18px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.15s;
        }
        input:focus {
            outline: none;
            border-color: #6366f1;
        }
        button {
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
        button:hover { background: #4338ca; }
        .error {
            background: #fef2f2;
            color: #b91c1c;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">NP</div>
        <h1>Payment Gateway Admin</h1>
        <p class="subtitle">Sign in to manage gateway settings</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('nepal-payment.admin.login.submit') }}">
            @csrf
            <label>Username</label>
            <input type="text" name="username" placeholder="admin" required autofocus>
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
            <button type="submit">Sign In</button>
        </form>
    </div>
</body>
</html>