<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COA Monthly Sharing - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        .share-table { font-size: 13px; width: 100%; }
        .share-table th { padding: 8px 6px; white-space: nowrap; }
        .share-table td { padding: 6px; vertical-align: middle; }
        .badge-shared { display:inline-block; padding:2px 10px; border-radius:12px; font-weight:700; font-size:12px; background:#d1fae5; color:#065f46; }
        .badge-not-shared { display:inline-block; padding:2px 10px; border-radius:12px; font-weight:700; font-size:12px; background:#fee2e2; color:#991b1b; }
        .badge-blocked { display:inline-block; padding:2px 10px; border-radius:12px; font-weight:700; font-size:12px; background:#fef3c7; color:#92400e; }
    </style>
</head>
<body>
    @php $settings = \App\Models\DtrSetting::getSettings(); @endphp
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
                <a href="{{ route('admin.users') }}"><span>Manage Users</span></a>
                <a href="{{ route('admin.password-reset-requests') }}"><span>Reset Requests</span></a>
                <a href="{{ route('admin.monitoring') }}"><span>Employee Monitoring</span></a>
                <a href="{{ route('admin.coa-shares') }}" class="active"><span>COA Sharing</span></a>
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
            <div class="admin-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:8px;">
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">COA Monthly Sharing</h1>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner" style="margin:0;">
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

            <div class="card" style="margin-bottom:24px;">
                <div class="card-header" style="font-weight:600;padding:12px 16px;border-bottom:1px solid var(--border);">Share DTRs with COA</div>
                <div class="card-body" style="padding:16px;">
                    <form method="get" action="{{ route('admin.coa-shares') }}" class="dtr-form" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <div class="form-group" style="min-width:140px;">
                            <label for="month">Month</label>
                            <select name="month" id="month" class="form-control">
                                @foreach (range(1, 12) as $m)
                                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="min-width:100px;">
                            <label for="year">Year</label>
                            <select name="year" id="year" class="form-control">
                                @for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="form-group" style="display:flex;flex-direction:column;">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Select Month</button>
                        </div>
                    </form>

                    <div style="margin-top:16px;padding:16px;background:var(--gray-50);border-radius:8px;border:1px solid var(--border);">
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                            <div>
                                <strong style="font-size:16px;">{{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</strong>
                                @if ($isCurrentShared)
                                    <span class="badge-shared" style="margin-left:10px;">Shared</span>
                                @elseif ($pendingCount > 0)
                                    <span class="badge-blocked" style="margin-left:10px;">{{ $pendingCount }} pending request(s)</span>
                                @else
                                    <span class="badge-not-shared" style="margin-left:10px;">Not Shared</span>
                                @endif
                            </div>
                            <button class="btn {{ $isCurrentShared ? 'btn-danger' : 'btn-accent' }}" onclick="toggleShare()" id="shareBtn" {{ !$isCurrentShared && $pendingCount > 0 ? 'disabled' : '' }}>
                                {{ $isCurrentShared ? 'Remove COA Access' : 'Share with COA' }}
                            </button>
                        </div>
                        @if ($pendingCount > 0 && !$isCurrentShared)
                            <p style="margin-top:8px;font-size:13px;color:var(--gray-600);">
                                <strong>{{ $pendingCount }} pending edit request(s)</strong> for this month must be approved or rejected before DTRs can be shared with COA.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:8px;">
                    <span style="font-weight:600;">Previously Shared Months</span>
                    <form method="GET" action="{{ route('admin.coa-shares') }}" style="display:flex;gap:8px;">
                        <input type="text" name="search" placeholder="Search by shared by..." value="{{ request('search') }}" style="padding:6px 10px;border:1.5px solid var(--gray-300);border-radius:6px;font-size:12px;background:var(--white);color:var(--gray-900);width:180px;outline:none;">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                        @if (request('search'))
                            <a href="{{ route('admin.coa-shares', ['month' => $month, 'year' => $year]) }}" class="btn btn-outline btn-sm">Clear</a>
                        @endif
                    </form>
                </div>
                <div class="card-body" style="padding:0;">
                    @if ($sharedMonths->isEmpty())
                        <p style="padding:24px;text-align:center;color:var(--gray-500);font-size:14px;">No months have been shared with COA yet.</p>
                    @else
                        <table class="share-table" style="font-size:13px;width:100%;">
                            <thead>
                                <tr style="border-bottom:2px solid var(--border);">
                                    <th style="padding:10px 12px;text-align:left;">Month</th>
                                    <th style="padding:10px 12px;text-align:left;">Shared By</th>
                                    <th style="padding:10px 12px;text-align:left;">Date Shared</th>
                                    <th style="padding:10px 12px;text-align:left;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sharedMonths as $share)
                                    @php
                                        $shareDate = \Carbon\Carbon::create($share->year, $share->month, 1);
                                    @endphp
                                    <tr style="border-bottom:1px solid var(--border);">
                                        <td style="padding:8px 12px;font-weight:600;">{{ $shareDate->format('F Y') }}</td>
                                        <td style="padding:8px 12px;">{{ $share->sharedBy->name ?? 'Unknown' }}</td>
                                        <td style="padding:8px 12px;">{{ $share->created_at->format('M d, Y h:i A') }}</td>
                                        <td style="padding:8px 12px;"><span class="badge-shared">Shared</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
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

        function toggleShare() {
            @if ($isCurrentShared)
                var msg = 'Remove COA access for {{ date("F Y", mktime(0, 0, 0, $month, 1, $year)) }}?';
            @else
                var msg = 'Share DTRs with COA for {{ date("F Y", mktime(0, 0, 0, $month, 1, $year)) }}?';
            @endif
            if (!confirm(msg)) return;
            fetch('{{ route("dtr.toggle-share") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ month: {{ $month }}, year: {{ $year }} })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
                else alert(data.message || 'An error occurred.');
            }).catch(function() { location.reload(); });
        }
    </script>
</body>
</html>