<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Logs - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
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
                <a href="{{ route('admin.password-reset-requests') }}"><span>Reset Requests</span></a>
                <a href="{{ route('admin.holidays') }}"><span>Holidays & Suspensions</span></a>
                <a href="{{ route('admin.work-arrangement') }}"><span>Work Arrangement</span></a>
                <a href="{{ route('admin.settings') }}"><span>Settings</span></a>
                <a href="{{ route('admin.logs') }}" class="active"><span>User Logs</span></a>
            </nav>
            <div class="sidebar-footer">
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%; margin-bottom:8px;" id="themeToggle">Dark Mode</button>
                <a href="{{ route('profile') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;margin-bottom:4px;">&#128100; My Profile</a>
                <a href="{{ route('dtr.index') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;">&larr; e-DTR Home</a>
            </div>
        </div>
        <div class="main-content">
            <div class="admin-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">User Logs</h1>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" action="{{ route('admin.logs') }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:end;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:12px;">Action</label>
                        <select name="action" class="form-control" style="padding:6px 10px;font-size:13px;">
                            <option value="">All</option>
                            @foreach ($actions as $a)
                                <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ ucfirst($a) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:12px;">User</label>
                        <select name="user_id" class="form-control" style="padding:6px 10px;font-size:13px;">
                            <option value="">All</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:12px;">From</label>
                        <input type="date" name="from" value="{{ request('from') }}" class="form-control" style="padding:6px 10px;font-size:13px;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:12px;">To</label>
                        <input type="date" name="to" value="{{ request('to') }}" class="form-control" style="padding:6px 10px;font-size:13px;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="{{ route('admin.logs') }}" class="btn btn-outline btn-sm">Clear</a>
                </form>
            </div>

            <div class="card">
                <p style="margin:0 0 12px;font-size:13px;color:var(--gray-600);">{{ $logs->total() }} log entries</p>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>Entity</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($logs as $log)
                                <tr>
                                    <td style="white-space:nowrap;font-size:12px;">{{ $log->created_at ? $log->created_at->format('M d, Y h:i A') : '&mdash;' }}</td>
                                    <td>{{ $log->user ? $log->user->name : 'System' }}</td>
                                    <td><span class="badge badge-{{ $log->action }}">{{ ucfirst($log->action) }}</span></td>
                                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->description }}">{{ $log->description ?: '&mdash;' }}</td>
                                    <td style="font-size:12px;">{{ $log->entity_type ? class_basename($log->entity_type) . ' #' . $log->entity_id : '&mdash;' }}</td>
                                    <td style="font-size:12px;">{{ $log->ip_address ?: '&mdash;' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No log entries found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:16px;">
                    {{ $logs->appends(request()->query())->links() }}
                </div>
            </div>
        </div>
    </div>

    <style>
        .badge-create, .badge-login { background: #d1fae5; color: #065f46; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap; }
        .badge-update, .badge-logout { background: #dbeafe; color: #1e40af; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap; }
        .badge-delete { background: #fce7f3; color: #9d174d; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap; }
        .badge-approve { background: #d1fae5; color: #065f46; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap; }
        .badge-reject { background: #fee2e2; color: #991b1b; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap; }
        .badge-export, .badge-print { background: #fef3c7; color: #92400e; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap; }
        .badge-info { background: #f3f4f6; color: #374151; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; white-space:nowrap; }
    </style>

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
