<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $employee->full_name }} - {{ $monthName }} {{ $year }} - {{ $settings['system_name'] ?? 'e-DTR Records' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
</head>
<body>
    @php $currentUser = auth()->user(); @endphp

    <div class="layout-sidebar">
        <div class="sidebar no-print">
            <div class="sidebar-header">
                @if (!empty($settings['logo_path']))
                    <img src="{{ asset('storage/' . $settings['logo_path']) }}" alt="Logo" style="height:32px;margin-bottom:4px;">
                @endif
                <h2>{{ $settings['system_name'] ?? 'e-DTR Records' }}</h2>
                <p>{{ $currentUser->name }}</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('dtr.index') }}" class="active">
                    <span>&#128196;</span> <span>e-DTR Records</span>
                </a>
                @if ($currentUser->is_super || $isSupervisor)
                    <a href="{{ route('supervisor.pending') }}">
                        <span>&#128276;</span> <span>Supervisor Panel</span>
                    </a>
                @endif
                @if ($currentUser->is_super)
                    <a href="{{ route('admin.dashboard') }}">
                        <span>&#9881;</span> <span>Admin</span>
                    </a>
                @endif
            </nav>
            <div class="sidebar-footer">
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%; margin-bottom:8px;" id="themeToggle">Dark Mode</button>

            </div>
        </div>

        <div class="main-content">
            <div class="navbar no-print" style="margin-bottom:20px;">
                <div class="navbar-left">
                    <h1 style="font-size:18px; color:var(--primary); margin:0;">{{ $employee->full_name }} &mdash; {{ $monthName }} {{ $year }}</h1>
                </div>
                <button onclick="window.print()" class="btn btn-primary btn-sm">Print / Save PDF</button>
                @php $unread = $currentUser->unreadNotifications; @endphp
                <div class="notif-pos">
                    <button class="notif-btn" onclick="toggleNotif()">&#128276;
                        @if ($unread->count() > 0)
                            <span class="notif-badge">{{ $unread->count() }}</span>
                        @endif
                    </button>
                    <div id="notifDropdown" class="notif-dropdown">
                        <div class="notif-header">Notifications</div>
                        @forelse ($unread as $notif)
                            @if ($notif->data['type'] === 'edit_request_submitted')
                                <a href="{{ route('supervisor.pending') }}" class="notif-item">
                            @else
                                @php
                                    $d = $notif->data['target_date'] ?? null;
                                    $m = $d ? date('n', strtotime($d)) : date('n');
                                    $y = $d ? date('Y', strtotime($d)) : date('Y');
                                    $ec = $notif->data['emp_code'] ?? '';
                                @endphp
                                <a href="{{ url('/dtr/show?emp=' . $ec . '&month=' . $m . '&year=' . $y) }}" class="notif-item">
                            @endif
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
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
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
                                                'on_leave' => 'On Leave',
                                                'wfh' => 'WFH',
                                                'special_order' => 'Special Order',
                                                'travel_order' => 'Travel Order',
                                                'work_suspension' => 'Work Suspension',
                                                'locator_slip' => 'Locator Slip',
                                            ];
                                            $details = '';
                                            if ($req->type === 'time_correction') {
                                                $details = $req->field . ': ' . ($req->old_value ?: '&mdash;') . ' &rarr; ' . $req->new_value;
                                            } elseif (in_array($req->type, ['halfday_am', 'halfday_pm']) && $req->new_value) {
                                                $label = $req->type === 'halfday_am' ? 'AM out' : 'PM in';
                                                $details = $label . ': ' . $req->new_value;
                                            } elseif ($req->type === 'on_leave' && $req->new_value) {
                                                $details = 'On Leave (' . $req->new_value . ' hrs)';
                                            } elseif (in_array($req->type, ['work_suspension', 'wfh', 'special_order', 'travel_order', 'locator_slip']) && $req->new_value && $req->new_value !== 'whole_day') {
                                                $details = $typeLabels[$req->type] ?? $req->type;
                                                if ($req->new_value === 'am') $details .= ' (AM)';
                                                elseif ($req->new_value === 'pm') $details .= ' (PM)';
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
                                                'on_leave' => 'On Leave',
                                                'wfh' => 'WFH',
                                                'special_order' => 'Special Order',
                                                'travel_order' => 'Travel Order',
                                                'work_suspension' => 'Work Suspension',
                                                'locator_slip' => 'Locator Slip',
                                            ];
                                            $details = '';
                                            if ($req->type === 'time_correction') {
                                                $details = $req->field . ': ' . ($req->old_value ?: '&mdash;') . ' &rarr; ' . $req->new_value;
                                            } elseif (in_array($req->type, ['halfday_am', 'halfday_pm']) && $req->new_value) {
                                                $label = $req->type === 'halfday_am' ? 'AM out' : 'PM in';
                                                $details = $label . ': ' . $req->new_value;
                                            } elseif ($req->type === 'on_leave' && $req->new_value) {
                                                $details = 'On Leave (' . $req->new_value . ' hrs)';
                                            } elseif (in_array($req->type, ['work_suspension', 'wfh', 'special_order', 'travel_order', 'locator_slip']) && $req->new_value && $req->new_value !== 'whole_day') {
                                                $details = $typeLabels[$req->type] ?? $req->type;
                                                if ($req->new_value === 'am') $details .= ' (AM)';
                                                elseif ($req->new_value === 'pm') $details .= ' (PM)';
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
                        <option value="absent">Absent</option>
                        <option value="on_leave">On Leave</option>
                        <option value="wfh">Work From Home (WFH)</option>
                        <option value="special_order">Special Order</option>
                        <option value="travel_order">Travel Order</option>
                        <option value="locator_slip">Locator Slip</option>
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

                <div id="soFields" style="display:none;">
                    <div class="form-group">
                        <label>Special Order Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="so_type" value="whole_day" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="so_type" value="am"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="so_type" value="pm"> PM
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_so_number">Special Order No.</label>
                        <input type="text" name="so_number" id="modal_so_number" placeholder="e.g. SO-2024-001" class="form-control">
                    </div>
                </div>

                <div id="toFields" style="display:none;">
                    <div class="form-group">
                        <label>Travel Order Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="to_type" value="whole_day" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="to_type" value="am"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="to_type" value="pm"> PM
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_to_number">Travel Order No.</label>
                        <input type="text" name="to_number" id="modal_to_number" placeholder="e.g. TO-2024-001" class="form-control">
                    </div>
                </div>

                <div id="dateRangeFields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_from_date">From Date</label>
                            <input type="date" name="from_date" id="modal_from_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="modal_to_date">To Date</label>
                            <input type="date" name="to_date" id="modal_to_date" class="form-control">
                        </div>
                    </div>
                </div>

                <div id="absentFields" style="display:none;">
                    <div class="form-group">
                        <label>Absent Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="absent_type" value="whole_day" onchange="setAbsentType('absent')" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="absent_type" value="am" onchange="setAbsentType('halfday_am')"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="absent_type" value="pm" onchange="setAbsentType('halfday_pm')"> PM
                            </label>
                        </div>
                    </div>
                </div>

                <div id="wfhFields" style="display:none;">
                    <div class="form-group">
                        <label>WFH Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="wfh_type" value="whole_day" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="wfh_type" value="am"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="wfh_type" value="pm"> PM
                            </label>
                        </div>
                    </div>
                </div>

                <div id="onLeaveFields" style="display:none;">
                    <div class="form-group">
                        <label for="modal_leave_hours">Leave Hours</label>
                        <input type="number" name="leave_hours" id="modal_leave_hours" min="0.5" max="24" step="0.5" placeholder="e.g. 8" class="form-control" style="width:140px;">
                    </div>
                </div>

                <div id="locatorSlipFields" style="display:none;">
                    <div class="form-group">
                        <label>Locator Slip Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ls_type" value="whole_day" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ls_type" value="am"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ls_type" value="pm"> PM
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_ls_number">Locator Slip No.</label>
                        <input type="text" name="ls_number" id="modal_ls_number" placeholder="e.g. LS-2024-001" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="modal_reason" id="modalReasonLabel">Reason</label>
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
            document.getElementById('modal_from_date').value = dateStr;
            document.getElementById('modal_to_date').value = dateStr;
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
            document.getElementById('modal_so_number').value = '';
            document.getElementById('modal_to_number').value = '';
            document.getElementById('modal_reason').value = '';
            document.querySelector('input[name="absent_type"][value="whole_day"]').checked = true;
            document.querySelector('input[name="wfh_type"][value="whole_day"]').checked = true;
            document.querySelector('input[name="so_type"][value="whole_day"]').checked = true;
            document.querySelector('input[name="to_type"][value="whole_day"]').checked = true;
            document.querySelector('input[name="ls_type"][value="whole_day"]').checked = true;
            document.getElementById('modal_ls_number').value = '';
            document.getElementById('modal_leave_hours').value = '';
        }

        function closeEditRequest() {
            document.getElementById('editRequestModal').classList.remove('active');
        }

        function toggleEditFields() {
            var type = document.getElementById('modal_type').value;
            var tcFields = document.getElementById('timeCorrectionFields');
            var soFields = document.getElementById('soFields');
            var toFields = document.getElementById('toFields');
            var absentFields = document.getElementById('absentFields');
            var wfhFields = document.getElementById('wfhFields');
            var onLeaveFields = document.getElementById('onLeaveFields');
            var locatorSlipFields = document.getElementById('locatorSlipFields');
            var dateRangeFields = document.getElementById('dateRangeFields');
            var modalDayLabel = document.getElementById('modalDayLabel');
            var fieldSelect = document.getElementById('modal_field');
            var newValueInput = document.getElementById('modal_new_value');
            var reasonLabel = document.getElementById('modalReasonLabel');
            var reasonInput = document.getElementById('modal_reason');

            tcFields.style.display = 'none';
            soFields.style.display = 'none';
            toFields.style.display = 'none';
            absentFields.style.display = 'none';
            wfhFields.style.display = 'none';
            onLeaveFields.style.display = 'none';
            locatorSlipFields.style.display = 'none';
            dateRangeFields.style.display = 'none';
            modalDayLabel.style.display = 'block';
            reasonLabel.textContent = 'Reason';
            reasonInput.placeholder = 'Explain why';
            fieldSelect.required = false;
            newValueInput.required = false;
            newValueInput.disabled = false;

            if (type === 'time_correction') {
                tcFields.style.display = 'block';
                fieldSelect.required = true;
                newValueInput.required = true;
            } else if (type === 'absent') {
                absentFields.style.display = 'block';
            } else if (type === 'wfh') {
                wfhFields.style.display = 'block';
            } else if (type === 'special_order') {
                soFields.style.display = 'block';
                dateRangeFields.style.display = 'block';
                modalDayLabel.style.display = 'none';
                reasonLabel.textContent = 'Title of Activity';
                reasonInput.placeholder = 'Enter the title of activity';
                document.getElementById('modal_so_number').required = true;
            } else if (type === 'travel_order') {
                toFields.style.display = 'block';
                dateRangeFields.style.display = 'block';
                modalDayLabel.style.display = 'none';
                reasonLabel.textContent = 'Title of Activity';
                reasonInput.placeholder = 'Enter the title of activity';
                document.getElementById('modal_to_number').required = true;
            } else if (type === 'on_leave') {
                onLeaveFields.style.display = 'block';
            } else if (type === 'locator_slip') {
                locatorSlipFields.style.display = 'block';
                reasonLabel.textContent = 'Title of Activity';
                reasonInput.placeholder = 'Enter the title of activity';
                document.getElementById('modal_ls_number').required = true;
            }
        }

        function setAbsentType(subType) {
            document.getElementById('modal_type').value = subType;
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
            if (d.classList.contains('active')) {
                var badge = document.querySelector('.notif-badge');
                if (badge) {
                    fetch('{{ route("notifications.mark-read") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        }
                    }).then(function() {
                        badge.remove();
                    });
                }
            }
        }
        document.addEventListener('click', function(e) {
            var d = document.getElementById('notifDropdown');
            if (!e.target.closest('#notifDropdown') && !e.target.closest('.notif-btn')) {
                d.classList.remove('active');
            }
        });
        document.getElementById('notifDropdown') && document.getElementById('notifDropdown').addEventListener('click', function(e) {
            var item = e.target.closest('.notif-item');
            if (!item) return;
            var notifId = item.getAttribute('data-notif-id');
            if (notifId) {
                e.preventDefault();
                var url = item.getAttribute('href');
                fetch('/notifications/' + notifId + '/mark-single-read', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                }).then(function() {
                    var badge = document.querySelector('.notif-badge');
                    if (badge) {
                        var count = parseInt(badge.textContent);
                        count--;
                        if (count <= 0) {
                            badge.remove();
                        } else {
                            badge.textContent = count;
                        }
                    }
                    item.remove();
                    window.location.href = url;
                }).catch(function() {
                    window.location.href = url;
                });
            }
        });
        function toggleWorkWeek(val) {
            fetch('{{ route("dtr.toggle-work-week") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ value: val })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            });
        }
        function toggleDayWorkWeek(dateStr, newType) {
            fetch('{{ route("dtr.toggle-day-work-week") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ target_date: dateStr, work_week_type: newType })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            });
        }
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
