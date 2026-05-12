<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - e-DTR Records</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
</head>
<body class="auth-body">
    <div class="auth-card">
        <h1>e-DTR Records</h1>
        <p class="auth-subtitle">Electronic Daily Time Record</p>
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
