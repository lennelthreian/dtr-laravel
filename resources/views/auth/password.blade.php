<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Change Password - {{ $settings['system_name'] ?? 'e-DTR Records' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
</head>
<body>
    @php $currentUser = auth()->user(); @endphp

    <div class="layout-sidebar">
        <div class="sidebar no-print">
            <div class="sidebar-header">
                @if (!empty($settings['logo_path']))
                    <img src="{{ asset('storage/' . $settings['logo_path']) }}" alt="Logo" style="height:32px;margin-bottom:4px;">
                @endif
                <h2>{{ $settings['system_name'] ?? 'e-DTR Records' }}</h2>
                <p>{{ $currentUser->name }}</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('dtr.dashboard') }}">
                    <span>&#128197;</span> <span>Dashboard</span>
                </a>
                <a href="{{ route('dtr.index') }}">
                    <span>&#128196;</span> <span>e-DTR Records</span>
                </a>
                @if ($currentUser->is_super || (isset($isSupervisor) && $isSupervisor))
                    <a href="{{ route('supervisor.pending') }}">
                        <span>&#128276;</span> <span>Supervisor Panel</span>
                    </a>
                @endif
                @if ($currentUser->is_super)
                    <a href="{{ route('admin.dashboard') }}">
                        <span>&#9881;</span> <span>Admin</span>
                    </a>
                @endif
                <a href="{{ route('profile') }}">
                    <span>&#128100;</span> <span>My Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%; margin-bottom:8px;" id="themeToggle">Dark Mode</button>
            </div>
        </div>

        <div class="main-content">
            <div class="navbar no-print" style="margin-bottom:20px;">
                <div class="navbar-left">
                    <h1 style="font-size:18px; color:var(--primary); margin:0;">Change Password</h1>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success" style="max-width:500px;">{{ session('success') }}</div>
            @endif

            <div class="card" style="max-width:500px;">
                <h2>Change Password</h2>
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
                        @error('current_password') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" name="password" id="password" class="form-control" required minlength="8" autocomplete="new-password">
                        @error('password') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm New Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <div style="display:flex;gap:10px;align-items:center;">
                            <button type="submit" class="btn btn-primary">Change Password</button>
                            <a href="{{ route('profile') }}" style="font-size:13px;color:var(--accent);">&larr; Back to Profile</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            var html = document.documentElement;
            var isDark = html.getAttribute('data-theme') === 'dark';
            if (isDark) {
                html.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                document.getElementById('themeToggle').textContent = 'Dark Mode';
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                document.getElementById('themeToggle').textContent = 'Light Mode';
            }
        }
        (function() {
            var btn = document.getElementById('themeToggle');
            if (btn && localStorage.getItem('theme') === 'dark') btn.textContent = 'Light Mode';
        })();
    </script>
</body>
</html>
