<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Failed</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 60px; background: #fdf4f4; }
        .card { background: #fff; max-width: 420px; margin: 0 auto; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { color: #c62828; }
        p { color: #444; }
        .icon { font-size: 48px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">❌</div>
        <h1>Payment Failed</h1>
        <p>{{ $message ?? 'Something went wrong with your payment.' }}</p>
    </div>
</body>
</html>