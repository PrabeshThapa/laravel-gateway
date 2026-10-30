<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Redirecting to eSewa...</title>
</head>
<body>
    <p>Redirecting to eSewa, please wait...</p>

    <form id="esewaForm" action="{{ $action }}" method="POST">
        @foreach ($fields as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
    </form>

    <script>
        document.getElementById('esewaForm').submit();
    </script>
</body>
</html>