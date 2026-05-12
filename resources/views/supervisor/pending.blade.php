<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Panel - Pending Requests</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
</head>
<body>
    <div class="layout-sidebar">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Supervisor</h2>
                <p>{{ auth()->user()->name }}</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('supervisor.pending') }}" class="active">
                    <span>&#128276;</span> <span>Pending Requests</span>
                    @php $pendingCount = $grouped->flatten()->count(); @endphp
                    @if ($pendingCount > 0)
                        <span class="badge">{{ $pendingCount }}</span>
                    @endif
                </a>
                <a href="{{ route('dtr.index') }}">
                    <span>&#128196;</span> <span>e-DTR Records</span>
                </a>
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
            <div class="navbar" style="margin-bottom:20px;">
                <div class="navbar-left">
                    <h1 style="font-size:20px; color:var(--primary); margin:0;">Pending Edit Requests</h1>
                </div>
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
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($grouped->isEmpty())
                <div class="card empty-state">
                    <p>No pending edit requests.</p>
                    <p>When employees submit requests, they will appear here.</p>
                </div>
            @else
                @foreach ($grouped as $employeeLabel => $requests)
                    <div class="card req-group">
                        <h3>{{ $employeeLabel }}</h3>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Details</th>
                                        <th>Reason</th>
                                        <th>Submitted</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($requests as $req)
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
                                            <td style="font-size:12px; color:var(--gray-500);">{{ $req->created_at->diffForHumans() }}</td>
                                            <td class="text-center nowrap">
                                                <button class="btn btn-outline btn-xs" onclick="viewRequest({{ $req->id }})">View</button>
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
                @endforeach
            @endif
        </div>
    </div>

    <div id="viewModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Edit Request Details</h2>
            <p class="modal-sub" id="viewEmployeeName"></p>
            <table class="detail-table">
                <tr><td>Type:</td><td id="viewType"></td></tr>
                <tr><td>Date:</td><td id="viewDate"></td></tr>
                <tr><td>Field:</td><td id="viewField"></td></tr>
                <tr><td>Old Value:</td><td id="viewOldValue"></td></tr>
                <tr><td>New Value:</td><td id="viewNewValue"></td></tr>
                <tr><td>Reason:</td><td id="viewReason"></td></tr>
                <tr><td>Submitted:</td><td id="viewSubmitted"></td></tr>
            </table>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeView()">Close</button>
            </div>
        </div>
    </div>

    <script>
        var requests = @json($grouped->flatten());

        function viewRequest(id) {
            var req = requests.find(function(r) { return r.id === id; });
            if (!req) return;
            document.getElementById('viewEmployeeName').textContent = req.employee ? (req.employee.first_name + ' ' + req.employee.last_name) : '';
            var typeLabels = {
                time_correction: 'Time Correction',
                absent: 'Whole Day Absent',
                halfday_am: 'Halfday (AM)',
                halfday_pm: 'Halfday (PM)',
                holiday: 'Holiday',
                wfh: 'WFH',
                special_order: 'Special Order',
                travel_order: 'Travel Order'
            };
            document.getElementById('viewType').textContent = typeLabels[req.type] || req.type;
            document.getElementById('viewDate').textContent = req.target_date;
            document.getElementById('viewField').textContent = req.field || '&mdash;';
            document.getElementById('viewOldValue').textContent = req.old_value || '&mdash;';
            document.getElementById('viewNewValue').textContent = req.new_value || '&mdash;';
            document.getElementById('viewReason').textContent = req.reason || '&mdash;';
            document.getElementById('viewSubmitted').textContent = req.created_at ? new Date(req.created_at).toLocaleString() : '&mdash;';
            document.getElementById('viewModal').classList.add('active');
        }

        function closeView() {
            document.getElementById('viewModal').classList.remove('active');
        }

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) closeView();
        });

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
