<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Employees - MBLISTTDA e-DTR System</title>
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
            <h1>Assign Employees</h1>
            <p>Assign employees to offices and sections</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <h2>Employees ({{ $employees->count() }})</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Emp Code</th>
                            <th>Current Office</th>
                            <th>Current Section</th>
                            <th class="text-center">Assign</th>
                            <th class="text-center">Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td><strong>{{ $employee->full_name }}</strong></td>
                                <td>{{ $employee->emp_code }}</td>
                                <td>{{ $employee->office ?: '&mdash;' }}</td>
                                <td>{{ $employee->section ?: '&mdash;' }}</td>
                                <td class="text-center">
                                    <button class="btn btn-outline btn-sm" onclick="showAssign({{ $employee->id }}, '{{ $employee->full_name }}', {{ $employee->office_id ?? 'null' }}, {{ $employee->section_id ?? 'null' }})">Assign</button>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.employees.reset-password', $employee) }}" onsubmit="return confirm('Reset password for {{ $employee->full_name }} to \"password\"?')">
                                        @csrf
                                        <button class="btn btn-outline btn-sm">Reset</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No employees.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="assignModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Assign Employee</h2>
            <p class="modal-sub" id="modalEmployeeName"></p>
            <form method="POST" id="assignForm">
                @csrf
                <div class="form-group">
                    <label for="modal_office_id">Office</label>
                    <select name="office_id" id="modal_office_id" class="form-control" onchange="updateSections()">
                        <option value="">&mdash; None &mdash;</option>
                        @foreach ($offices as $office)
                            <option value="{{ $office->id }}">{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="modal_section_id">Section</label>
                    <select name="section_id" id="modal_section_id" class="form-control">
                        <option value="">&mdash; None &mdash;</option>
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
        const offices = @json($offices);

        function showAssign(id, name, officeId, sectionId) {
            document.getElementById('modalEmployeeName').textContent = name;
            document.getElementById('assignForm').action = '{{ url('/admin/employees') }}/' + id + '/assign';
            document.getElementById('modal_office_id').value = officeId || '';
            updateSections(function() {
                document.getElementById('modal_section_id').value = sectionId || '';
            });
            document.getElementById('assignModal').classList.add('active');
        }

        function closeAssign() {
            document.getElementById('assignModal').classList.remove('active');
        }

        function updateSections(callback) {
            const officeId = document.getElementById('modal_office_id').value;
            const sectionSelect = document.getElementById('modal_section_id');
            sectionSelect.innerHTML = '<option value="">&mdash; None &mdash;</option>';
            if (officeId) {
                const office = offices.find(o => o.id == officeId);
                if (office && office.sections) {
                    office.sections.forEach(function(s) {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = s.name;
                        sectionSelect.appendChild(opt);
                    });
                }
            }
            if (callback) callback();
        }

        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) closeAssign();
        });
    </script>
</body>
</html>
