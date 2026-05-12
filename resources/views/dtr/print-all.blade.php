<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All DTRs - {{ $monthName }} {{ $year }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <style>
        body { background: var(--white); padding: 20px; }
        .print-header { text-align: center; margin-bottom: 24px; }
        .print-header h1 { font-size: 20px; color: var(--primary); margin-bottom: 4px; }
        .print-header p { font-size: 14px; color: var(--gray-600); }
        .dtr-page { margin-bottom: 0; }
        .employee-divider { page-break-before: always; }
        .no-print { display: block; }
        @media print {
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
                if ($day['has_punch'] && $dow <= 5) {
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
</body>
</html>
