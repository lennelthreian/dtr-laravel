<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sections - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
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
                <a href="{{ route('admin.sections') }}" class="active"><span>Manage Sections</span></a>
                <a href="{{ route('admin.employees') }}"><span>Assign Employees</span></a>
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
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">Manage Sections</h1>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card">
                <h2>Add Section</h2>
                <form method="POST" action="{{ route('admin.sections.store') }}" style="display:flex; gap:10px; flex-wrap:wrap;">
                    @csrf
                    <select name="office_id" required class="form-control" style="flex:1; min-width:200px;">
                        <option value="">-- Select Office --</option>
                        @foreach ($offices as $office)
                            <option value="{{ $office->id }}">{{ $office->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" placeholder="Section name" required class="form-control" style="flex:2; min-width:200px;">
                    <button type="submit" class="btn btn-primary">Add</button>
                </form>
                @error('name')
                    <div style="color:var(--danger); font-size:12px; margin-top:5px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="card">
                <h2>Sections ({{ $sections->count() }})</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Office</th>
                                <th>Supervisor</th>
                                <th class="text-center">Supervisor ID</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sections as $section)
                                <tr>
                                    <td><strong>{{ $section->name }}</strong></td>
                                    <td>{{ $section->office->name }}</td>
                                    <td>
                                        @if ($section->supervisor)
                                            {{ $section->supervisor->full_name }}
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $section->supervisor_id ?? '&mdash;' }}</td>
                                    <td class="text-center nowrap">
                                        <button class="btn btn-outline btn-xs" onclick="showAssign({{ $section->id }}, '{{ $section->name }}', {{ $section->supervisor_id ?? 'null' }})">Set Supervisor</button>
                                        <form method="POST" action="{{ route('admin.sections.delete', $section) }}"
                                              onsubmit="return confirm('Delete this section?')" style="display:inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-xs">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted" style="padding:24px;">No sections yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="assignModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Set Section Supervisor</h2>
            <p class="modal-sub" id="modalSectionName"></p>
            <form method="POST" id="assignForm">
                @csrf
                <div class="form-group">
                    <label for="modal_supervisor_id">Supervisor</label>
                    <select name="supervisor_id" id="modal_supervisor_id" class="form-control">
                        <option value="">&mdash; None &mdash;</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->emp_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeAssign()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
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
        function showAssign(id, name, supervisorId) {
            document.getElementById('modalSectionName').textContent = name;
            document.getElementById('assignForm').action = '{{ url('/admin/sections') }}/' + id + '/assign-supervisor';
            document.getElementById('modal_supervisor_id').value = supervisorId || '';
            document.getElementById('assignModal').classList.add('active');
        }
        function closeAssign() {
            document.getElementById('assignModal').classList.remove('active');
        }
        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) closeAssign();
        });
    </script>
</body>
</html>
