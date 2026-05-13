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
            [data-theme="dark"] {
                --gray-50: #f8f9fa;
                --gray-100: #f0f2f5;
                --gray-200: #e9ecef;
                --gray-300: #dee2e6;
                --gray-400: #ced4da;
                --gray-500: #adb5bd;
                --gray-600: #6c757d;
                --gray-700: #495057;
                --gray-800: #343a40;
                --gray-900: #212529;
                --white: #ffffff;
                --primary: #2d5a27;
                --primary-light: #3d7a35;
                --primary-dark: #1e401a;
                --accent: #1a6b1a;
                --accent-light: #228b22;
                --danger: #c0392b;
                --danger-light: #e74c3c;
                --warning: #f39c12;
                --warning-bg: #fff8e1;
            }
            body { padding: 0; }
            .no-print { display: none !important; }
            .employee-divider { page-break-before: always; }
            .dtr-cols { gap: 0; justify-content: center; }
            .dtr-half { flex: none; border: none; padding: 0; }
            .dtr-half:last-child { margin-left: 60px; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:20px;">
        <button onclick="window.print()" class="btn btn-primary">Print All DTRs</button>
        <button onclick="toggleTheme()" class="btn btn-outline" style="margin-left:8px;" id="themeToggle">Dark Mode</button>
        <a href="{{ route('dtr.index') }}" class="btn btn-outline" style="margin-left:8px;">&larr; Back</a>
    </div>

    <div class="print-header no-print">
        <h1>All Employees' DTR</h1>
        <p>{{ $monthName }} {{ $year }} &mdash; {{ count($allDtrs) }} employees</p>
    </div>

    @foreach ($allDtrs as $index => $item)
        @php
            $presentDays = 0; $totalMin = 0; $totalLate = 0; $totalUndertime = 0;
            foreach ($item['dtrData'] as $dayNum => $day) {
                $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
                if ($day['has_punch'] && $dow <= $settings['max_dow']) {
                    $presentDays++;
                    if ($day['total_hours']) {
                        $parts = explode(':', $day['total_hours']);
                        $totalMin += (int) $parts[0] * 60 + (int) $parts[1];
                    }
                    if (strpos($day['remarks'], 'Late:') !== false) {
                        preg_match_all('/(\d+):(\d+)/', $day['remarks'], $m);
                        for ($i = 0; $i < count($m[0]); $i++) {
                            $totalLate += (int) $m[1][$i] * 60 + (int) $m[2][$i];
                        }
                    }
                    if (strpos($day['remarks'], 'UT:') !== false) {
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
    </script>
</body>
</html>
