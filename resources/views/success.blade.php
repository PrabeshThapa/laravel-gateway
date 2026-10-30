<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Successful</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 60px; background: #f4f8f4; }
        .card { background: #fff; max-width: 420px; margin: 0 auto; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { color: #2e7d32; }
        p { color: #444; }
        .icon { font-size: 48px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Payment Successful</h1>
        <p>Transaction ID: {{ $result->transactionUuid }}</p>
        @if($result->amount)
            <p>Amount: NPR {{ $result->amount }}</p>
        @endif
    </div>
</body>
</html>