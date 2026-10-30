<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Redirecting to Nepal Payment...</title>
</head>
<body>
    <p>Redirecting to Nepal Payment Gateway, please wait...</p>

    <form id="onepgForm" action="{{ $action }}" method="POST">
        @foreach ($fields as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
    </form>

    <script>
        document.getElementById('onepgForm').submit();
    </script>
</body>
</html>