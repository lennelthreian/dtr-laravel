<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Employee Monitoring - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        .monitor-table { font-size: 13px; width: 100%; }
        .monitor-table th { padding: 8px 6px; white-space: nowrap; }
        .monitor-table td { padding: 6px; vertical-align: middle; }
        .monitor-table .emp-name { font-weight: 600; }
        .badge-count { display:inline-block; padding:2px 10px; border-radius:12px; font-weight:700; font-size:12px; min-width:28px; text-align:center; }
        .badge-late { background:#fee2e2; color:#991b1b; }
        .badge-ut { background:#fef3c7; color:#92400e; }
        .badge-combined { background:#fce7f3; color:#9d174d; }
        .badge-absent { background:#f3e8ff; color:#6b21a8; }
        .badge-warn { background:#dc2626; color:#fff; }
        .badge-ok { background:#d1fae5; color:#065f46; }
        .memo-btn { font-size:11px; padding:4px 12px; border-radius:4px; border:none; cursor:pointer; font-weight:600; transition:opacity .15s; }
        .memo-btn:hover { opacity:.8; }
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
                <a href="{{ route('admin.monitoring') }}" class="active"><span>Employee Monitoring</span></a>
                <a href="{{ route('admin.coa-shares') }}"><span>COA Sharing</span></a>
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
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">Employee Monitoring</h1>
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

            <div class="card" style="padding:16px;margin-bottom:16px;">
                <form method="GET" action="{{ route('admin.monitoring') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;">
                        <label for="month" style="font-size:12px;">Month</label>
                        <select name="month" id="month" class="form-control" style="padding:6px 10px;font-size:13px;">
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="year" style="font-size:12px;">Year</label>
                        <select name="year" id="year" class="form-control" style="padding:6px 10px;font-size:13px;">
                            @foreach (range(date('Y') - 2, date('Y') + 1) as $y)
                                <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="search" style="font-size:12px;">Search Employee</label>
                        <input type="text" name="search" id="search" placeholder="Name or emp code..." value="{{ request('search') }}" class="form-control" style="padding:6px 10px;font-size:13px;width:180px;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:18px;">View</button>
                    @if (request('search'))
                        <a href="{{ route('admin.monitoring', ['month' => $month, 'year' => $year]) }}" class="btn btn-outline btn-sm" style="margin-top:18px;">Clear</a>
                    @endif
                </form>
            </div>

            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                    <h2 style="margin:0;font-size:16px;">{{ date('F', mktime(0, 0, 0, $month, 1)) }} {{ $year }} &mdash; Employee Summary ({{ count($stats) }})</h2>
                </div>
                <div class="table-wrap">
                    <table class="monitor-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Office</th>
                                <th class="text-center">Late<br><small>(days)</small></th>
                                <th class="text-center">Undertime<br><small>(days)</small></th>
                                <th class="text-center">Late + UT<br><small>(days)</small></th>
                                <th class="text-center">Memo</th>
                                <th class="text-center">Absent<br><small>(days)</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($stats as $s)
                                @php
                                    $combined = $s['late_undertime_days'];
                                    $needsMemo = $combined >= 10;
                                @endphp
                                <tr>
                                    <td class="emp-name">{{ $s['employee']->full_name }}</td>
                                    <td>{{ $s['employee']->officeModel ? $s['employee']->officeModel->name : ($s['employee']->office ?: '&mdash;') }}</td>
                                    <td class="text-center"><span class="badge-count badge-late">{{ $s['late_days'] }}</span></td>
                                    <td class="text-center"><span class="badge-count badge-ut">{{ $s['undertime_days'] }}</span></td>
                                    <td class="text-center">
                                        <span class="badge-count {{ $needsMemo ? 'badge-warn' : 'badge-combined' }}">{{ $combined }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if ($needsMemo)
                                            <button class="memo-btn" style="background:#dc2626;color:#fff;" onclick="issueMemo('{{ $s['employee']->id }}', '{{ str_replace("'", "\\'", $s['employee']->full_name) }}', {{ $combined }})">Issue Memo</button>
                                        @else
                                            <span class="badge-count badge-ok" style="font-size:11px;">{{ 10 - $combined }} left</span>
                                        @endif
                                    </td>
                                    <td class="text-center"><span class="badge-count badge-absent">{{ $s['absent_days'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">No employee data found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="memoModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Issue Memo</h2>
            <p style="margin-bottom:12px;color:var(--gray-600);">
                Issue a memo for <strong id="memoEmployeeName"></strong> &mdash;
                <span id="memoCombinedCount" style="color:#dc2626;font-weight:700;"></span> days of late/undertime.
            </p>
            <form method="POST" action="" id="memoForm">
                @csrf
                <input type="hidden" name="employee_id" id="memoEmployeeId">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <div class="form-group">
                    <label for="memo_reason">Memo Reason / Notes</label>
                    <textarea name="notes" id="memo_reason" rows="3" class="form-control" placeholder="Enter details for the memo..." style="width:100%;resize:vertical;"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeMemo()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Issue Memo</button>
                </div>
            </form>
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

        function issueMemo(empId, empName, combinedDays) {
            document.getElementById('memoEmployeeId').value = empId;
            document.getElementById('memoEmployeeName').textContent = empName;
            document.getElementById('memoCombinedCount').textContent = combinedDays;
            document.getElementById('memo_reason').value = '';
            document.getElementById('memoForm').action = '{{ route("admin.issue-memo") }}';
            document.getElementById('memoModal').classList.add('active');
        }

        function closeMemo() {
            document.getElementById('memoModal').classList.remove('active');
        }

        document.getElementById('memoModal').addEventListener('click', function(e) {
            if (e.target === this) closeMemo();
        });
    </script>
</body>
</html>

