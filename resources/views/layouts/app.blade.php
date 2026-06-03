<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ $settings['system_name'] ?? 'e-DTR Records' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    @if (!empty($settings['logo_path']))
    <style>
        .auth-body[data-logo] {
            background:
                linear-gradient(rgba(255,255,255,0.78), rgba(255,255,255,0.78)),
                url('{{ asset('storage/' . $settings['logo_path']) }}') center / 400px auto no-repeat,
                linear-gradient(135deg, #2d5a27 0%, #3d7a35 100%);
        }
    </style>
    @endif
</head>
<body class="auth-body" @if (!empty($settings['logo_path'])) data-logo @endif>
    <div class="auth-card">
        @if (!empty($settings['logo_path']))
            <img src="{{ asset('storage/' . $settings['logo_path']) }}" alt="Logo" style="display:block;height:48px;margin:0 auto 12px;">
        @endif
        <h1>{{ $settings['system_name'] ?? 'e-DTR Records' }}</h1>
        <p class="auth-subtitle">Electronic Daily Time Record</p>
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
