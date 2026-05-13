<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $settings['system_name'] ?? 'e-DTR Records' }}</title>
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
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%;">Logout</button>
                </form>
            </div>
        </div>

        <div class="main-content">
            <div class="navbar no-print" style="margin-bottom:20px;">
                <div class="navbar-left">
                    <h1 style="font-size:18px; color:var(--primary); margin:0;">e-DTR Records</h1>
                </div>
                @if ($dtrData)
                    <button onclick="window.print()" class="btn btn-primary btn-sm">Print / Save PDF</button>
                    @if ($currentUser->is_super && $month && $year)
                        <a href="{{ route('dtr.print-all', ['month' => $month, 'year' => $year]) }}" class="btn btn-accent btn-sm">Print All DTRs</a>
                    @endif
                @endif
                @php $isFdww = ($settings['four_day_work_week'] ?? '0') === '1'; @endphp
                <div class="ww-toggle-group">
                    <button class="ww-toggle-btn{{ $isFdww ? '' : ' active' }}" onclick="toggleWorkWeek('0')">5-day WW</button>
                    <button class="ww-toggle-btn{{ $isFdww ? ' active' : '' }}" onclick="toggleWorkWeek('1')">4-day WW</button>
                </div>
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
                                <a href="{{ route('supervisor.pending') }}" class="notif-item" data-notif-id="{{ $notif->id }}">
                            @else
                                @php
                                    $d = $notif->data['target_date'] ?? null;
                                    $m = $d ? date('n', strtotime($d)) : date('n');
                                    $y = $d ? date('Y', strtotime($d)) : date('Y');
                                    $ec = $notif->data['emp_code'] ?? '';
                                @endphp
                                <a href="{{ url('/dtr/show?emp=' . $ec . '&month=' . $m . '&year=' . $y) }}" class="notif-item" data-notif-id="{{ $notif->id }}">
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
            </div>

            @if (session('success'))
                <div class="alert alert-success no-print" style="max-width:1000px;">{{ session('success') }}</div>
            @endif

            <div class="dtr-layout">
                <div class="dtr-sidebar-form no-print">
                    <div class="card">
                        <h2>{{ $currentUser->is_super || $isSupervisor ? 'Select Employee' : 'My Daily Time Record' }}</h2>
                        <form method="get" action="{{ route('dtr.index') }}" class="dtr-form">
                            @if ($currentUser->is_super || $isSupervisor)
                                <div class="form-group">
                                    <label for="emp">Employee</label>
                                    <select name="emp" id="emp" required class="form-control">
                                        <option value="">-- Select Employee --</option>
                                        @foreach ($employees as $emp)
                                            <option value="{{ $emp->emp_code }}" {{ $emp->emp_code == request('emp') ? 'selected' : '' }}>
                                                {{ $emp->full_name }} ({{ $emp->emp_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                @foreach ($employees as $emp)
                                    <input type="hidden" name="emp" value="{{ $emp->emp_code }}">
                                    <p style="font-size:15px; margin-bottom:18px; color:var(--gray-800);">
                                        <strong>{{ $emp->full_name }}</strong> ({{ $emp->emp_code }})
                                    </p>
                                @endforeach
                            @endif
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="month">Month</label>
                                    <select name="month" id="month" class="form-control">
                                        @foreach (range(1, 12) as $m)
                                            <option value="{{ $m }}" {{ $m == ($month ?? date('m')) ? 'selected' : '' }}>
                                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="year">Year</label>
                                    <select name="year" id="year" class="form-control">
                                        @for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                                            <option value="{{ $y }}" {{ $y == ($year ?? date('Y')) ? 'selected' : '' }}>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Generate DTR</button>
                        </form>
                    </div>
                </div>

                <div class="dtr-main-content">
                    @if ($employee && $month && $year)
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
                    @else
                        <div class="card empty-state">
                            <p style="color:var(--gray-500); font-size:14px; text-align:center; padding:40px 20px; margin:0;">
                                Select an employee, month, and year then click <strong>Generate DTR</strong> to view the Daily Time Record.
                            </p>
                        </div>
                    @endif
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
            document.querySelector('input[name="absent_type"][value="whole_day"]').checked = true;
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
            var absentFields = document.getElementById('absentFields');
            var fieldSelect = document.getElementById('modal_field');
            var newValueInput = document.getElementById('modal_new_value');
            var halfdayTime = document.getElementById('modal_halfday_time');
            var halfdayLabel = document.getElementById('halfdayTimeLabel');

            tcFields.style.display = 'none';
            hdFields.style.display = 'none';
            soFields.style.display = 'none';
            toFields.style.display = 'none';
            absentFields.style.display = 'none';
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
            } else if (type === 'absent') {
                absentFields.style.display = 'block';
            } else if (type === 'special_order') {
                soFields.style.display = 'block';
                document.getElementById('modal_so_number').required = true;
            } else if (type === 'travel_order') {
                toFields.style.display = 'block';
                document.getElementById('modal_to_number').required = true;
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
