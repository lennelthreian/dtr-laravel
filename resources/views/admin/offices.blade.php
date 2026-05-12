<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Divisions - MBLISTTDA e-DTR System</title>
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
            <h1>Manage Divisions</h1>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <h2>Add Division</h2>
            <form method="POST" action="{{ route('admin.offices.store') }}" style="display:flex; gap:10px;">
                @csrf
                <input type="text" name="name" placeholder="Division name" required class="form-control" style="flex:1;">
                <button type="submit" class="btn btn-primary">Add</button>
            </form>
            @error('name')
                <div style="color:var(--danger); font-size:12px; margin-top:5px;">{{ $message }}</div>
            @enderror
        </div>

        <div class="card">
            <h2>Offices ({{ $offices->count() }})</h2>
            <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th class="text-center">Sections</th>
                                <th>Supervisor</th>
                                <th>Senior Manager</th>
                                <th>OIC</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($offices as $office)
                                <tr>
                                    <td><strong>{{ $office->name }}</strong></td>
                                    <td class="text-center">{{ $office->sections_count }}</td>
                                    <td>
                                        @if ($office->supervisor)
                                            {{ $office->supervisor->full_name }}
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($office->seniorManager)
                                            {{ $office->seniorManager->full_name }}
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($office->oic)
                                            {{ $office->oic->full_name }}
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="text-center nowrap">
                                        <button class="btn btn-outline btn-xs" onclick="showAssign({{ $office->id }}, '{{ $office->name }}', {{ $office->supervisor_id ?? 'null' }})">Set Supervisor</button>
                                        <button class="btn btn-outline btn-xs" onclick="showAssignSM({{ $office->id }}, '{{ $office->name }}', {{ $office->senior_manager_id ?? 'null' }})">Set Senior Manager</button>
                                        <button class="btn btn-outline btn-xs" onclick="showAssignOIC({{ $office->id }}, '{{ $office->name }}', {{ $office->oic_id ?? 'null' }})">Set OIC</button>
                                        <form method="POST" action="{{ route('admin.offices.delete', $office) }}"
                                              onsubmit="return confirm('Delete this office and all its sections?')" style="display:inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-xs">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No offices yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>
        </div>
    </div>

    <div id="assignModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Set Division Supervisor</h2>
            <p class="modal-sub" id="modalOfficeName"></p>
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

    <div id="assignSM" class="modal-overlay">
        <div class="modal-box">
            <h2>Set Senior Manager</h2>
            <p class="modal-sub" id="modalSMOfficeName"></p>
            <form method="POST" id="assignSMForm">
                @csrf
                <div class="form-group">
                    <label for="modal_senior_manager_id">Senior Manager</label>
                    <select name="senior_manager_id" id="modal_senior_manager_id" class="form-control">
                        <option value="">&mdash; None &mdash;</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->emp_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeAssignSM()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="assignOIC" class="modal-overlay">
        <div class="modal-box">
            <h2>Set OIC</h2>
            <p class="modal-sub" id="modalOICOfficeName"></p>
            <form method="POST" id="assignOICForm">
                @csrf
                <div class="form-group">
                    <label for="modal_oic_id">OIC</label>
                    <select name="oic_id" id="modal_oic_id" class="form-control">
                        <option value="">&mdash; None &mdash;</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->emp_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeAssignOIC()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAssign(id, name, supervisorId) {
            document.getElementById('modalOfficeName').textContent = name;
            document.getElementById('assignForm').action = '{{ url('/admin/offices') }}/' + id + '/assign-supervisor';
            document.getElementById('modal_supervisor_id').value = supervisorId || '';
            document.getElementById('assignModal').classList.add('active');
        }
        function closeAssign() {
            document.getElementById('assignModal').classList.remove('active');
        }
        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) closeAssign();
        });
        function showAssignSM(id, name, seniorManagerId) {
            document.getElementById('modalSMOfficeName').textContent = name;
            document.getElementById('assignSMForm').action = '{{ url('/admin/offices') }}/' + id + '/assign-senior-manager';
            document.getElementById('modal_senior_manager_id').value = seniorManagerId || '';
            document.getElementById('assignSM').classList.add('active');
        }
        function closeAssignSM() {
            document.getElementById('assignSM').classList.remove('active');
        }
        document.getElementById('assignSM').addEventListener('click', function(e) {
            if (e.target === this) closeAssignSM();
        });
        function showAssignOIC(id, name, oicId) {
            document.getElementById('modalOICOfficeName').textContent = name;
            document.getElementById('assignOICForm').action = '{{ url('/admin/offices') }}/' + id + '/assign-oic';
            document.getElementById('modal_oic_id').value = oicId || '';
            document.getElementById('assignOIC').classList.add('active');
        }
        function closeAssignOIC() {
            document.getElementById('assignOIC').classList.remove('active');
        }
        document.getElementById('assignOIC').addEventListener('click', function(e) {
            if (e.target === this) closeAssignOIC();
        });
    </script>
</body>
</html>
