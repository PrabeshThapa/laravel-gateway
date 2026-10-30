<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="refresh" content="0;url={{ $redirect_url }}">
    <title>Redirecting...</title>
</head>
<body>
    <p>Redirecting to payment page, please wait...</p>
    <script>
        window.location.href = "{{ $redirect_url }}";
    </script>
</body>
</html>