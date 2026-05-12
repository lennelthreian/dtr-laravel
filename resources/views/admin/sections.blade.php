<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sections - MBLISTTDA e-DTR System</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
</head>
<body>
    <div class="container">
        <div class="navbar">
            <div class="navbar-left">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-sm">&larr; Dashboard</a>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button class="btn btn-outline btn-sm">Logout</button>
            </form>
        </div>

        <div class="page-header">
            <h1>Manage Sections</h1>
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
