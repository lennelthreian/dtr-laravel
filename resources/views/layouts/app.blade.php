<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - MBLISTTDA e-DTR System</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h1>MBLISTTDA e-DTR System</h1>
        <p class="auth-subtitle">Electronic Daily Time Record</p>
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
