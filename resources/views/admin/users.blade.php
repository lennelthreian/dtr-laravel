<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
</head>
<body>
    <div class="layout-sidebar">
        <div class="sidebar">
            <div class="sidebar-header">
                @if (!empty($settings['logo_path']))
                    <img src="{{ asset('storage/' . $settings['logo_path']) }}" alt="Logo" style="height:32px;margin-bottom:4px;">
                @endif
                <h2>Admin Panel</h2>
                <p>{{ $settings['system_name'] ?? 'e-DTR System' }}</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('admin.dashboard') }}"><span>Dashboard</span></a>
                <a href="{{ route('admin.offices') }}"><span>Manage Divisions</span></a>
                <a href="{{ route('admin.sections') }}"><span>Manage Sections</span></a>
                <a href="{{ route('admin.employees') }}"><span>Assign Employees</span></a>
                <a href="{{ route('admin.users') }}" class="active"><span>Manage Users</span></a>
                <a href="{{ route('admin.password-reset-requests') }}"><span>Reset Requests</span></a>
                <a href="{{ route('admin.monitoring') }}"><span>Employee Monitoring</span></a>
                <a href="{{ route('admin.holidays') }}"><span>Holidays & Suspensions</span></a>
                <a href="{{ route('admin.work-arrangement') }}"><span>Work Arrangement</span></a>
                <a href="{{ route('admin.logs') }}"><span>User Logs</span></a>
                <a href="{{ route('admin.settings') }}"><span>Settings</span></a>
            </nav>
            <div class="sidebar-footer">
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%; margin-bottom:8px;" id="themeToggle">Dark Mode</button>
                <a href="{{ route('profile') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;margin-bottom:4px;">&#128100; My Profile</a>
                <a href="{{ route('dtr.index') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;">&larr; e-DTR Home</a>
            </div>
        </div>
        <div class="main-content">
            <div class="admin-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">Manage Users</h1>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                    <h2 style="margin:0;">User Accounts ({{ $users->count() }})</h2>
                    <input type="text" id="userSearch" placeholder="Search by name, username, or email..." style="padding:8px 12px;border:1.5px solid var(--gray-300);border-radius:6px;font-size:13px;background:var(--white);color:var(--gray-900);width:280px;outline:none;" oninput="filterUsers(this.value)">
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Super Admin</th>
                                <th>COA</th>
                                <th class="text-center">Super Action</th>
                                <th class="text-center">COA Action</th>
                                <th class="text-center">Password</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                            @forelse ($users as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->username }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @if ($user->is_super)
                                            <span style="color:var(--success);font-weight:600;">Yes</span>
                                        @else
                                            <span class="text-muted">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($user->is_coa)
                                            <span style="color:#e74c3c;font-weight:600;">Yes</span>
                                        @else
                                            <span class="text-muted">No</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($user->id === auth()->id())
                                            <span class="text-muted" style="font-size:12px;">(you)</span>
                                        @else
                                            <form method="POST" action="{{ route('admin.users.toggle-super', $user) }}" onsubmit="return confirm('{{ $user->is_super ? 'Remove' : 'Grant' }} super admin privileges for {{ $user->name }}?')">
                                                @csrf
                                                @if ($user->is_super)
                                                    <button class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">Revoke</button>
                                                @else
                                                    <button class="btn btn-outline btn-sm" style="color:var(--primary);border-color:var(--primary);">Grant</button>
                                                @endif
                                            </form>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="{{ route('admin.users.toggle-coa', $user) }}" onsubmit="return confirm('{{ $user->is_coa ? 'Remove' : 'Grant' }} COA privileges for {{ $user->name }}?')">
                                            @csrf
                                            @if ($user->is_coa)
                                                <button class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">Revoke</button>
                                            @else
                                                <button class="btn btn-outline btn-sm" style="color:#e74c3c;border-color:#e74c3c;">Grant</button>
                                            @endif
                                        </form>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-outline btn-sm" style="color:#f39c12;border-color:#f39c12;" onclick="openResetModal('{{ $user->name }}', '{{ route('admin.users.reset-password', $user) }}')">Reset</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">No users found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <form id="resetPasswordForm" method="POST" action="" style="display:none;">
        @csrf
    </form>

    <div id="resetPasswordModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Reset Password</h2>
            <p style="margin-bottom:16px;color:var(--gray-600);">
                Reset password for <strong id="resetUserName"></strong> to
                <code style="background:var(--gray-100);padding:2px 8px;border-radius:4px;font-size:14px;">password</code>?
            </p>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeResetModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitReset()">Confirm Reset</button>
            </div>
        </div>
    </div>

    <script>
        var resetUrl = '';

        function openResetModal(name, url) {
            resetUrl = url;
            document.getElementById('resetUserName').textContent = name;
            document.getElementById('resetPasswordModal').classList.add('active');
        }

        function closeResetModal() {
            document.getElementById('resetPasswordModal').classList.remove('active');
            resetUrl = '';
        }

        function submitReset() {
            document.getElementById('resetPasswordForm').action = resetUrl;
            document.getElementById('resetPasswordForm').submit();
        }

        document.getElementById('resetPasswordModal').addEventListener('click', function(e) {
            if (e.target === this) closeResetModal();
        });

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

        function filterUsers(query) {
            var q = query.toLowerCase().trim();
            var rows = document.querySelectorAll('#userTableBody tr');
            rows.forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
