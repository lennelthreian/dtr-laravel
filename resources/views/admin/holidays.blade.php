<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holidays & Work Suspensions - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        .calendar-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; background:var(--gray-300); border-radius:8px; overflow:hidden; }
        .calendar-grid > div { background:var(--white); padding:10px 6px; text-align:center; min-height:80px; }
        .calendar-grid .day-header { font-weight:600; font-size:12px; color:var(--gray-500); text-transform:uppercase; padding:8px 6px; min-height:auto; }
        .calendar-grid .day-num { font-size:13px; font-weight:600; color:var(--gray-700); margin-bottom:4px; }
        .calendar-grid .day-empty { background:var(--gray-100); min-height:auto; }
        .holiday-badge, .ws-badge { display:inline-block; font-size:10px; padding:2px 6px; border-radius:4px; margin:2px 0; cursor:default; }
        .holiday-badge { background:#dbeafe; color:#1e40af; }
        .ws-badge { background:#fce7f3; color:#9d174d; }
        .holiday-delete { font-size:10px; color:var(--danger); cursor:pointer; margin-left:2px; text-decoration:none; }
        .holiday-delete:hover { text-decoration:underline; }
        .month-nav { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .month-nav h2 { margin:0; font-size:18px; color:var(--primary); }
        .month-nav a { font-size:13px; }
    </style>
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
                <a href="{{ route('admin.holidays') }}" class="active"><span>Holidays & Suspensions</span></a>
                <a href="{{ route('admin.settings') }}"><span>Settings</span></a>
            </nav>
            <div class="sidebar-footer">
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%; margin-bottom:8px;" id="themeToggle">Dark Mode</button>
                <a href="{{ route('dtr.index') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;">&larr; e-DTR Home</a>
            </div>
        </div>
        <div class="main-content">
            <div class="admin-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">Holidays & Work Suspensions</h1>
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

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul style="margin:0;padding-left:16px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="display:flex;gap:24px;flex-wrap:wrap;">
                <div style="flex:1;min-width:300px;">
                    <div class="card">
                        <h2>Set Global Holiday / Work Suspension</h2>
                        <form method="POST" action="{{ route('admin.holidays.store') }}">
                            @csrf
                            <div style="display:flex;flex-direction:column;gap:12px;">
                                <div>
                                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);">Date</label>
                                    <input type="date" name="target_date" required class="form-control" value="{{ old('target_date', sprintf('%04d-%02d-%02d', $year, $month, 1)) }}">
                                </div>
                                <div>
                                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);">Type</label>
                                    <select name="type" required class="form-control">
                                        <option value="holiday" {{ old('type') === 'holiday' ? 'selected' : '' }}>Holiday</option>
                                        <option value="work_suspension" {{ old('type') === 'work_suspension' ? 'selected' : '' }}>Work Suspension</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);">Coverage</label>
                                    <select name="value" required class="form-control">
                                        <option value="whole_day" {{ old('value') === 'whole_day' ? 'selected' : '' }}>Whole Day</option>
                                        <option value="am" {{ old('value') === 'am' ? 'selected' : '' }}>AM Only</option>
                                        <option value="pm" {{ old('value') === 'pm' ? 'selected' : '' }}>PM Only</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:13px;font-weight:600;color:var(--gray-700);">Description (optional)</label>
                                    <input type="text" name="description" class="form-control" placeholder="e.g. Independence Day" value="{{ old('description') }}">
                                </div>
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </form>
                    </div>

                    <div class="card" style="margin-top:16px;">
                        <h2>Entries This Month</h2>
                        @php $entries = collect($weeks)->flatten(1)->filter(fn($d) => $d && $d['holiday']) @endphp
                        @if ($entries->isEmpty())
                            <p style="color:var(--gray-500);font-size:13px;">No holidays or work suspensions this month.</p>
                        @else
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                @foreach ($entries as $day)
                                    @php $h = $day['holiday'] @endphp
                                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--gray-100);border-radius:6px;font-size:13px;">
                                        <div>
                                            <strong>{{ date('M j, Y', strtotime($h->target_date)) }}</strong>
                                            <span class="{{ $h->type === 'holiday' ? 'holiday-badge' : 'ws-badge' }}" style="margin-left:8px;">
                                                {{ $h->type === 'holiday' ? 'Holiday' : 'Work Suspension' }}
                                            </span>
                                            <span style="color:var(--gray-500);margin-left:4px;">
                                                ({{ $h->value === 'whole_day' ? 'Whole Day' : strtoupper($h->value) }})
                                            </span>
                                            @if ($h->description)
                                                <span style="color:var(--gray-600);margin-left:8px;">&mdash; {{ $h->description }}</span>
                                            @endif
                                        </div>
                                        <form method="POST" action="{{ route('admin.holidays.delete', $h) }}" style="display:inline" onsubmit="return confirm('Remove this entry?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:13px;">&times;</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div style="flex:2;min-width:400px;">
                    <div class="card">
                        <div class="month-nav">
                            <a href="{{ route('admin.holidays', ['month' => $month > 1 ? $month - 1 : 12, 'year' => $month > 1 ? $year : $year - 1]) }}" class="btn btn-outline btn-sm">&larr; Previous</a>
                            <h2>{{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</h2>
                            <a href="{{ route('admin.holidays', ['month' => $month < 12 ? $month + 1 : 1, 'year' => $month < 12 ? $year : $year + 1]) }}" class="btn btn-outline btn-sm">Next &rarr;</a>
                        </div>
                        <div class="calendar-grid">
                            <div class="day-header">Mon</div>
                            <div class="day-header">Tue</div>
                            <div class="day-header">Wed</div>
                            <div class="day-header">Thu</div>
                            <div class="day-header">Fri</div>
                            <div class="day-header">Sat</div>
                            <div class="day-header">Sun</div>
                            @foreach ($weeks as $week)
                                @foreach ($week as $day)
                                    @if ($day === null)
                                        <div class="day-empty"></div>
                                    @else
                                        <div>
                                            <div class="day-num">{{ $day['day'] }}</div>
                                            @if ($day['holiday'])
                                                @if ($day['holiday']->type === 'holiday')
                                                    <div class="holiday-badge">
                                                        Holiday
                                                        @if ($day['holiday']->value !== 'whole_day')
                                                            ({{ strtoupper($day['holiday']->value) }})
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="ws-badge">
                                                        Suspension
                                                        @if ($day['holiday']->value !== 'whole_day')
                                                            ({{ strtoupper($day['holiday']->value) }})
                                                        @endif
                                                    </div>
                                                @endif
                                                @if ($day['holiday']->description)
                                                    <div style="font-size:9px;color:var(--gray-500);line-height:1.2;">{{ $day['holiday']->description }}</div>
                                                @endif
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            @endforeach
                        </div>
                    </div>
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
    </script>
</body>
</html>
