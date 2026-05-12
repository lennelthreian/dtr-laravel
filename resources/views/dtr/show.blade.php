<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>e-DTR - {{ $employee->full_name }} - {{ $monthName }} {{ $year }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
</head>
<body>
    @php
        $_isSupervisor = auth()->user()->is_super;
        if (!$_isSupervisor) {
            $_dtrU = App\Models\DtrUser::where('emp_code', auth()->user()->emp_code)->first();
            if ($_dtrU) {
                $_isSupervisor = App\Models\Section::where('supervisor_id', $_dtrU->id)->exists()
                    || App\Models\Office::where('supervisor_id', $_dtrU->id)->exists();
            }
        }
    @endphp

    <div class="layout-sidebar">
        <div class="sidebar no-print">
            <div class="sidebar-header">
                <h2>MBLISTTDA e-DTR</h2>
                <p>{{ auth()->user()->name }}</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('dtr.index') }}" class="active">
                    <span>&#128196;</span> <span>e-DTR Records</span>
                </a>
                @if (auth()->user()->is_super || $_isSupervisor)
                    <a href="{{ route('supervisor.pending') }}">
                        <span>&#128276;</span> <span>Supervisor Panel</span>
                    </a>
                @endif
                @if (auth()->user()->is_super)
                    <a href="{{ route('admin.dashboard') }}">
                        <span>&#9881;</span> <span>Admin</span>
                    </a>
                @endif
            </nav>
            <div class="sidebar-footer">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%;">Logout</button>
                </form>
            </div>
        </div>

        <div class="main-content">
            <div class="navbar no-print" style="margin-bottom:20px;">
                <div class="navbar-left">
                    <h1 style="font-size:18px; color:var(--primary); margin:0;">{{ $employee->full_name }} &mdash; {{ $monthName }} {{ $year }}</h1>
                </div>
                <button onclick="window.print()" class="btn btn-primary btn-sm">Print / Save PDF</button>
                @php $unread = auth()->user()->unreadNotifications; @endphp
                <div class="notif-pos">
                    <button class="notif-btn" onclick="toggleNotif()">&#128276;
                        @if ($unread->count() > 0)
                            <span class="notif-badge">{{ $unread->count() }}</span>
                        @endif
                    </button>
                    <div id="notifDropdown" class="notif-dropdown">
                        <div class="notif-header">Notifications</div>
                        @forelse ($unread as $notif)
                            <a href="{{ route('supervisor.pending') }}" class="notif-item">
                                <strong>{{ $notif->data['message'] }}</strong>
                                <div class="notif-time">{{ $notif->created_at->diffForHumans() }}</div>
                            </a>
                        @empty
                            <div style="padding:24px; text-align:center; color:var(--gray-500); font-size:13px;">No new notifications</div>
                        @endforelse
                        @if ($unread->count() > 0)
                            <div class="notif-footer">
                                <form method="POST" action="{{ route('notifications.mark-read') }}">
                                    @csrf
                                    <button type="submit" style="background:none; border:none; color:var(--accent); font-size:12px; font-weight:600; cursor:pointer;">Mark all as read</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
                <form method="get" action="{{ route('dtr.show') }}" class="inline-form" style="display:inline-flex; align-items:center; gap:4px;">
                    <input type="hidden" name="emp" value="{{ $employee->emp_code }}">
                    <select name="month" onchange="this.form.submit()" style="padding:6px 10px; border:1.5px solid var(--gray-300); border-radius:4px; font-size:13px; background:var(--white);">
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                        @endforeach
                    </select>
                    <select name="year" onchange="this.form.submit()" style="padding:6px 10px; border:1.5px solid var(--gray-300); border-radius:4px; font-size:13px; background:var(--white);">
                        @for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                            <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success no-print" style="max-width:1000px;">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-error no-print" style="max-width:1000px;">
                    <strong>Error:</strong> Please check the form and try again.
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($isSupervisor && $pendingRequests->isNotEmpty())
                <div class="no-print" style="max-width:1000px; margin-bottom:15px;">
                    <div class="card" style="padding:15px 20px;">
                        <h3 style="font-size:14px; margin-bottom:10px; color:#856404; border:none; margin:0 0 10px; padding:0;">Pending Edit Requests ({{ $pendingRequests->count() }})</h3>
                        <div class="table-wrap">
                            <table style="font-size:12px;">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Details</th>
                                        <th>Reason</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pendingRequests as $req)
                                        @php
                                            $typeLabels = [
                                                'time_correction' => 'Time Correction',
                                                'absent' => 'Whole Day Absent',
                                                'halfday_am' => 'Halfday (AM)',
                                                'halfday_pm' => 'Halfday (PM)',
                                                'holiday' => 'Holiday',
                                                'wfh' => 'WFH',
                                                'special_order' => 'Special Order',
                                                'travel_order' => 'Travel Order',
                                            ];
                                            $details = '';
                                            if ($req->type === 'time_correction') {
                                                $details = $req->field . ': ' . ($req->old_value ?: '&mdash;') . ' &rarr; ' . $req->new_value;
                                            } elseif (in_array($req->type, ['halfday_am', 'halfday_pm']) && $req->new_value) {
                                                $label = $req->type === 'halfday_am' ? 'AM out' : 'PM in';
                                                $details = $label . ': ' . $req->new_value;
                                            } else {
                                                $details = $typeLabels[$req->type] ?? $req->type;
                                            }
                                        @endphp
                                        <tr>
                                            <td>{{ $req->target_date->format('M d, Y') }}</td>
                                            <td><strong>{{ $typeLabels[$req->type] ?? $req->type }}</strong></td>
                                            <td>{!! $details !!}</td>
                                            <td style="max-width:200px;">{{ $req->reason }}</td>
                                            <td class="text-center nowrap">
                                                <form method="POST" action="{{ route('dtr.edit-request.approve', $req) }}" style="display:inline">
                                                    @csrf
                                                    <button class="btn btn-accent btn-xs">Approve</button>
                                                </form>
                                                <form method="POST" action="{{ route('dtr.edit-request.reject', $req) }}" style="display:inline" onsubmit="return confirm('Reject this request?')">
                                                    @csrf
                                                    <button class="btn btn-danger btn-xs">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if ($approvedRequests->isNotEmpty())
                <div class="no-print" style="max-width:1000px; margin-bottom:15px;">
                    <div class="card" style="padding:15px 20px;">
                        <h3 style="font-size:14px; margin-bottom:10px; color:var(--accent); border:none; margin:0 0 10px; padding:0;">Approved Edit Requests ({{ $approvedRequests->count() }})</h3>
                        <div class="table-wrap">
                            <table style="font-size:12px;">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Details</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($approvedRequests as $req)
                                        @php
                                            $typeLabels = [
                                                'time_correction' => 'Time Correction',
                                                'absent' => 'Whole Day Absent',
                                                'halfday_am' => 'Halfday (AM)',
                                                'halfday_pm' => 'Halfday (PM)',
                                                'holiday' => 'Holiday',
                                                'wfh' => 'WFH',
                                                'special_order' => 'Special Order',
                                                'travel_order' => 'Travel Order',
                                            ];
                                            $details = '';
                                            if ($req->type === 'time_correction') {
                                                $details = $req->field . ': ' . ($req->old_value ?: '&mdash;') . ' &rarr; ' . $req->new_value;
                                            } elseif (in_array($req->type, ['halfday_am', 'halfday_pm']) && $req->new_value) {
                                                $label = $req->type === 'halfday_am' ? 'AM out' : 'PM in';
                                                $details = $label . ': ' . $req->new_value;
                                            } else {
                                                $details = $typeLabels[$req->type] ?? $req->type;
                                            }
                                        @endphp
                                        <tr>
                                            <td>{{ $req->target_date->format('M d, Y') }}</td>
                                            <td><strong>{{ $typeLabels[$req->type] ?? $req->type }}</strong></td>
                                            <td>{!! $details !!}</td>
                                            <td style="max-width:200px;">{{ $req->reason }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <div class="dtr-page">
                <div class="dtr-cols">
                    <div class="dtr-half">
                        @include('dtr._content')
                    </div>
                    <div class="dtr-half">
                        @include('dtr._content')
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (isset($isOwnDtr) && $isOwnDtr)
    <div id="editRequestModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Request DTR Edit</h2>
            <p class="modal-sub" id="modalDayLabel">Day </p>
            <form method="POST" action="{{ route('dtr.edit-request.store') }}">
                @csrf
                <input type="hidden" name="target_date" id="modal_target_date">

                <div class="form-group">
                    <label for="modal_type">Type</label>
                    <select name="type" id="modal_type" required class="form-control" onchange="toggleEditFields()">
                        <option value="time_correction">Time Correction</option>
                        <option value="absent">Whole Day Absent</option>
                        <option value="halfday_am">Halfday Absent (AM)</option>
                        <option value="halfday_pm">Halfday Absent (PM)</option>
                        <option value="holiday">Holiday</option>
                        <option value="wfh">Work From Home (WFH)</option>
                        <option value="special_order">Special Order</option>
                        <option value="travel_order">Travel Order</option>
                    </select>
                </div>

                <div id="timeCorrectionFields">
                    <div class="form-group">
                        <label for="modal_field">Field to correct</label>
                        <select name="field" id="modal_field" class="form-control">
                            <option value="am_in">AM Arrival</option>
                            <option value="am_out">AM Departure</option>
                            <option value="pm_in">PM Arrival</option>
                            <option value="pm_out">PM Departure</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal_old_value">Current value</label>
                        <input type="text" name="old_value" id="modal_old_value" readonly class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="modal_new_value">Correct value</label>
                        <input type="text" name="new_value" id="modal_new_value" placeholder="e.g. 08:00" class="form-control">
                    </div>
                </div>

                <div id="halfdayFields" style="display:none;">
                    <div class="form-group">
                        <label for="modal_halfday_time" id="halfdayTimeLabel">Time</label>
                        <input type="text" name="new_value" id="modal_halfday_time" placeholder="e.g. 10:00" class="form-control">
                    </div>
                </div>

                <div id="soFields" style="display:none;">
                    <div class="form-group">
                        <label for="modal_so_number">Special Order No.</label>
                        <input type="text" name="so_number" id="modal_so_number" placeholder="e.g. SO-2024-001" class="form-control">
                    </div>
                </div>

                <div id="toFields" style="display:none;">
                    <div class="form-group">
                        <label for="modal_to_number">Travel Order No.</label>
                        <input type="text" name="to_number" id="modal_to_number" placeholder="e.g. TO-2024-001" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="modal_reason">Reason</label>
                    <textarea name="reason" id="modal_reason" required rows="3" placeholder="Explain why" class="form-control"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeEditRequest()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditRequest(day, amIn, amOut, pmIn, pmOut) {
            var dateStr = ('{{ $year }}' + '-' + String('{{ $month }}').padStart(2, '0') + '-' + String(day).padStart(2, '0'));
            document.getElementById('modal_target_date').value = dateStr;
            document.getElementById('modalDayLabel').textContent = 'Day ' + day + ' (' + dateStr + ')';
            document.getElementById('modal_type').value = 'time_correction';

            var fieldSelect = document.getElementById('modal_field');
            var oldValueInput = document.getElementById('modal_old_value');
            var newValueInput = document.getElementById('modal_new_value');

            function updateOldValue() {
                var field = fieldSelect.value;
                var values = { am_in: amIn, am_out: amOut, pm_in: pmIn, pm_out: pmOut };
                oldValueInput.value = values[field] || '';
            }

            fieldSelect.onchange = updateOldValue;
            updateOldValue();
            toggleEditFields();
            document.getElementById('editRequestModal').classList.add('active');
            newValueInput.value = '';
            document.getElementById('modal_halfday_time').value = '';
            document.getElementById('modal_so_number').value = '';
            document.getElementById('modal_to_number').value = '';
            document.getElementById('modal_reason').value = '';
        }

        function closeEditRequest() {
            document.getElementById('editRequestModal').classList.remove('active');
        }

        function toggleEditFields() {
            var type = document.getElementById('modal_type').value;
            var tcFields = document.getElementById('timeCorrectionFields');
            var hdFields = document.getElementById('halfdayFields');
            var soFields = document.getElementById('soFields');
            var toFields = document.getElementById('toFields');
            var fieldSelect = document.getElementById('modal_field');
            var newValueInput = document.getElementById('modal_new_value');
            var halfdayTime = document.getElementById('modal_halfday_time');
            var halfdayLabel = document.getElementById('halfdayTimeLabel');

            tcFields.style.display = 'none';
            hdFields.style.display = 'none';
            soFields.style.display = 'none';
            toFields.style.display = 'none';
            fieldSelect.required = false;
            newValueInput.required = false;
            halfdayTime.required = false;
            halfdayTime.disabled = true;
            newValueInput.disabled = false;

            if (type === 'time_correction') {
                tcFields.style.display = 'block';
                fieldSelect.required = true;
                newValueInput.required = true;
            } else if (type === 'halfday_am') {
                hdFields.style.display = 'block';
                halfdayLabel.textContent = 'Time out (AM departure)';
                fieldSelect.value = 'am_out';
                halfdayTime.required = false;
                halfdayTime.disabled = false;
                halfdayTime.placeholder = 'e.g. 10:00';
            } else if (type === 'halfday_pm') {
                hdFields.style.display = 'block';
                halfdayLabel.textContent = 'Time in (PM arrival)';
                fieldSelect.value = 'pm_in';
                halfdayTime.required = false;
                halfdayTime.disabled = false;
                halfdayTime.placeholder = 'e.g. 14:00';
            } else if (type === 'special_order') {
                soFields.style.display = 'block';
                document.getElementById('modal_so_number').required = true;
            } else if (type === 'travel_order') {
                toFields.style.display = 'block';
                document.getElementById('modal_to_number').required = true;
            }
        }

        document.getElementById('editRequestModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditRequest();
        });
    </script>
    @endif

    <script>
        function toggleNotif() {
            var d = document.getElementById('notifDropdown');
            d.classList.toggle('active');
        }
        document.addEventListener('click', function(e) {
            var d = document.getElementById('notifDropdown');
            if (!e.target.closest('#notifDropdown') && !e.target.closest('.notif-btn')) {
                d.classList.remove('active');
            }
        });
    </script>
</body>
</html>
