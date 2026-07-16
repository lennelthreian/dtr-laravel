<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All DTRs - {{ $monthName }} {{ $year }} - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        body { background: var(--white); padding: 20px; }
        .print-header { text-align: center; margin-bottom: 24px; }
        .print-header h1 { font-size: 20px; color: var(--primary); margin-bottom: 4px; }
        .print-header p { font-size: 14px; color: var(--gray-600); }
        .dtr-page { margin-bottom: 0; }
        .employee-divider { page-break-before: always; }
        .no-print { display: block; }
        @media print {
            .no-print { display: none !important; }
            .employee-divider { page-break-before: always; }
            .dtr-cols { gap: 16px; justify-content: center; }
            .dtr-half { flex: 1; border: none; padding: 0; min-width: 0; }
            .dtr-half:last-child { margin-left: 0; }
            .has-val, .no-entry, .edited-val,
            .dtr-table tr.weekend td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:20px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <button onclick="window.print()" class="btn btn-primary">Print All DTRs</button>
        @if (auth()->user()->is_super)
            @if ($pendingCount > 0 && !$isShared)
                <button class="btn btn-outline" disabled style="opacity:0.6;cursor:not-allowed;" title="{{ $pendingCount }} pending edit request(s) must be resolved first">
                    Share with COA ({{ $pendingCount }} pending)
                </button>
            @else
                <button class="btn {{ $isShared ? 'btn-danger' : 'btn-accent' }}" onclick="toggleShare()" id="shareBtn">{{ $isShared ? 'Unshare from COA' : 'Share with COA' }}</button>
            @endif
        @endif
        <form method="get" action="{{ route('dtr.print-all') }}" style="display:inline-flex;align-items:center;gap:4px;margin-left:8px;">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="year" value="{{ $year }}">
            <select name="cut_off" onchange="this.form.submit()" style="padding:6px 10px; border:1.5px solid var(--gray-300); border-radius:4px; font-size:13px; background:var(--white);">
                <option value="all" {{ ($cutOff ?? 'all') === 'all' ? 'selected' : '' }}>Whole Month</option>
                <option value="1" {{ ($cutOff ?? '') === '1' ? 'selected' : '' }}>1st Cut-Off (1-15)</option>
                <option value="2" {{ ($cutOff ?? '') === '2' ? 'selected' : '' }}>2nd Cut-Off (16-End)</option>
            </select>
        </form>
        <button onclick="toggleTheme()" class="btn btn-outline" id="themeToggle">Dark Mode</button>
        <a href="{{ route('dtr.index', ['month' => $month, 'year' => $year, 'cut_off' => $cutOff ?? 'all']) }}" class="btn btn-outline" style="margin-left:auto;">&larr; Back</a>
    </div>
    @if (session('success'))
        <div class="alert alert-success no-print">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger no-print">{{ session('error') }}</div>
    @endif

    @if ($pendingCount > 0)
        <div class="alert alert-warning no-print" style="margin-bottom:16px;">
            <strong>{{ $pendingCount }} pending edit request(s)</strong> for this month must be approved or rejected before DTRs can be shared with COA.
        </div>
    @endif

    <div class="print-header no-print">
        <h1>All Employees' DTR</h1>
        <p>{{ $cutOffLabel }} {{ $year }} &mdash; {{ count($allDtrs) }} employees</p>
    </div>

    @foreach ($allDtrs as $index => $item)
        @php
            $presentDays = 0; $totalMin = 0; $totalLate = 0; $totalUndertime = 0;
            $empDefaultWW = $item['employee']->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');
            foreach ($item['dtrData'] as $dayNum => $day) {
                if ($dayNum < $cutOffStartDay || $dayNum > $cutOffEndDay) continue;
                $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
                $dayMaxDow = isset($day['work_week_type']) ? ($day['work_week_type'] === '4-day' ? 4 : 5) : ($empDefaultWW === '4-day' ? 4 : 5);
                if (!empty($day['has_punch']) && $dow <= $dayMaxDow && empty($day['is_holiday']) && empty($day['is_work_suspension'])) {
                    $presentDays++;
                    if (!empty($day['total_hours'])) {
                        $parts = explode(':', $day['total_hours']);
                        $totalMin += (int) $parts[0] * 60 + (int) $parts[1];
                    }
                    if (!empty($day['remarks']) && strpos($day['remarks'], 'Late:') !== false) {
                        preg_match_all('/(?:AM|PM)\s+(\d+):(\d+)/', $day['remarks'], $m);
                        for ($i = 0; $i < count($m[0]); $i++) {
                            $totalLate += (int) $m[1][$i] * 60 + (int) $m[2][$i];
                        }
                    }
                    if (!empty($day['remarks']) && strpos($day['remarks'], 'UT:') !== false) {
                        preg_match('/UT: (\d+):(\d+)/', $day['remarks'], $u);
                        if (isset($u[1])) {
                            $totalUndertime += (int) $u[1] * 60 + (int) $u[2];
                        }
                    }
                }
            }
            $totalMinutes = $totalMin;
        @endphp
        <div class="{{ $index > 0 ? 'employee-divider' : '' }}" style="margin-bottom:40px;">
            <div class="dtr-page">
                <div class="dtr-cols">
                    <div class="dtr-half">
                        @include('dtr._content', ['employee' => $item['employee'], 'dtrData' => $item['dtrData']])
                    </div>
                    <div class="dtr-half">
                        @include('dtr._content', ['employee' => $item['employee'], 'dtrData' => $item['dtrData']])
                    </div>
                </div>
            </div>
        </div>
    @endforeach
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

        function toggleShare() {
            if (!confirm('{{ $isShared ? 'Remove COA access for' : 'Share DTRs with COA for' }} {{ $monthName }} {{ $year }}?')) return;
            fetch('{{ route("dtr.toggle-share") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ month: {{ $month }}, year: {{ $year }} })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            }).catch(function() { location.reload(); });
        }
    </script>
</body>
</html>
