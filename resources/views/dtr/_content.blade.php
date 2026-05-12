<div style="font-family:Arial, sans-serif; font-style:italic; font-size:9px; margin-bottom:4px; text-align:left;">Civil Service Form No. 48</div>
<table class="dtr-header-table">
    <tr>
        <td colspan="2" style="text-align:center;">
            <h1 style="font-size:13px; letter-spacing:1px; margin:0 0 4px; border-bottom:1px solid #000; display:inline-block; padding-bottom:2px;">DAILY TIME RECORD</h1>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="text-align:center; padding:2px 0;">
            <h1 style="font-size:14px; margin:0; text-decoration:underline; font-weight:700;">{{ $employee->full_name }}</h1>
            <div style="font-size:8px; margin-top:1px; color:var(--gray-600);">(Name)</div>
        </td>
    </tr>
    <tr>
        <td colspan="2" class="dtr-header-right" style="text-align:left;">
            <h2 style="font-size:12px; margin:2px 0 0; text-align:center; font-weight:400;">For the month of <u>{{ $monthName }} {{ $year }}</u></h2>
        </td>
    </tr>
</table>

@php
    $totalWeekdays = 0; $presentWeekdays = 0; $totalSaturdays = 0; $presentSaturdays = 0;
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d)));
        if ($dow <= 5) { $totalWeekdays++; if (isset($dtrData[$d]) && $dtrData[$d]['has_punch']) $presentWeekdays++; }
        if ($dow == 6) { $totalSaturdays++; if (isset($dtrData[$d]) && $dtrData[$d]['has_punch']) $presentSaturdays++; }
    }
@endphp
<div style="display:flex; justify-content:space-between; font-size:9px; margin:4px 0; font-style:italic;">
    <div>Official hours for<br>arrival and departure</div>
    <div style="text-align:right;">Regular days ({{ $totalWeekdays }})<br>Saturdays ({{ $totalSaturdays }})</div>
</div>


<table class="dtr-table">
    <thead>
        <tr>
            <th rowspan="2">Day</th>
            <th colspan="2">AM</th>
            <th colspan="2">PM</th>
            <th rowspan="2">Total<br>Hours</th>
            <th rowspan="2">Remarks</th>
            @if (isset($isOwnDtr) && $isOwnDtr)
                <th rowspan="2" class="no-print action-col">Edit</th>
            @endif
        </tr>
        <tr>
            <th>Arrival</th>
            <th>Departure</th>
            <th>Arrival</th>
            <th>Departure</th>
        </tr>
    </thead>
    <tbody>
        @for ($d = 1; $d <= $daysInMonth; $d++)
            @php
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $dayOfWeek = date('l', strtotime($dateStr));
                $isWeekend = in_array($dayOfWeek, ['Saturday', 'Sunday']);
                $hasData = isset($dtrData[$d]) && $dtrData[$d]['has_punch'];
            @endphp
            @php
                $edited = isset($dtrData[$d]['is_edited']) && $dtrData[$d]['is_edited'];
                $rowClass = trim(($isWeekend && !$hasData ? 'weekend ' : '') . ($hasData ? 'has-data ' : '') . ($edited ? 'edited' : ''));
            @endphp
            <tr class="{{ $rowClass }}">
                <td class="day-col">
                    {{ $d }}
                    <span class="dow">{{ substr($dayOfWeek, 0, 3) }}</span>
                </td>
                @if (!empty($dtrData[$d]['so_number']))
                    <td class="time-col has-val">SO: {{ $dtrData[$d]['so_number'] }}</td>
                    <td class="time-col has-val">SO: {{ $dtrData[$d]['so_number'] }}</td>
                    <td class="time-col has-val">SO: {{ $dtrData[$d]['so_number'] }}</td>
                    <td class="time-col has-val">SO: {{ $dtrData[$d]['so_number'] }}</td>
                    <td class="hours-col"></td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif (!empty($dtrData[$d]['to_number']))
                    <td class="time-col has-val">TO: {{ $dtrData[$d]['to_number'] }}</td>
                    <td class="time-col has-val">TO: {{ $dtrData[$d]['to_number'] }}</td>
                    <td class="time-col has-val">TO: {{ $dtrData[$d]['to_number'] }}</td>
                    <td class="time-col has-val">TO: {{ $dtrData[$d]['to_number'] }}</td>
                    <td class="hours-col"></td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif (!empty($dtrData[$d]['is_wfh']))
                    <td class="time-col has-val">WFH</td>
                    <td class="time-col has-val">WFH</td>
                    <td class="time-col has-val">WFH</td>
                    <td class="time-col has-val">WFH</td>
                    <td class="hours-col"></td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif (!empty($dtrData[$d]['is_holiday']))
                    <td class="time-col has-val">Holiday</td>
                    <td class="time-col has-val">Holiday</td>
                    <td class="time-col has-val">Holiday</td>
                    <td class="time-col has-val">Holiday</td>
                    <td class="hours-col"></td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif ($hasData)
                    @php $ai = $dtrData[$d]['am_in']; $ao = $dtrData[$d]['am_out']; $pi = $dtrData[$d]['pm_in']; $po = $dtrData[$d]['pm_out']; @endphp
                    <td class="time-col{{ empty($ai) ? ' no-entry' : ($edited ? ' edited-val' : ' has-val') }}">{{ $ai }}</td>
                    <td class="time-col{{ empty($ao) ? ' no-entry' : ($edited ? ' edited-val' : ' has-val') }}">{{ $ao }}</td>
                    <td class="time-col{{ empty($pi) ? ' no-entry' : ($edited ? ' edited-val' : ' has-val') }}">{{ $pi }}</td>
                    <td class="time-col{{ empty($po) ? ' no-entry' : ($edited ? ' edited-val' : ' has-val') }}">{{ $po }}</td>
                    <td class="hours-col">{{ $dtrData[$d]['total_hours'] }}</td>
                    <td class="remarks-col">
                        {{ $dtrData[$d]['remarks'] }}
                        @if ($edited)
                            <span class="edited-badge">edited</span>
                        @endif
                    </td>
                @else
                    <td class="time-col{{ !$isWeekend ? ' no-entry' : '' }}"></td>
                    <td class="time-col{{ !$isWeekend ? ' no-entry' : '' }}"></td>
                    <td class="time-col{{ !$isWeekend ? ' no-entry' : '' }}"></td>
                    <td class="time-col{{ !$isWeekend ? ' no-entry' : '' }}"></td>
                    <td class="hours-col"></td>
                    <td class="remarks-col"></td>
                @endif
                @if (isset($isOwnDtr) && $isOwnDtr)
                    <td class="action-col no-print">
                        @php
                            $ai = isset($dtrData[$d]) ? $dtrData[$d]['am_in'] : '';
                            $ao = isset($dtrData[$d]) ? $dtrData[$d]['am_out'] : '';
                            $pi = isset($dtrData[$d]) ? $dtrData[$d]['pm_in'] : '';
                            $po = isset($dtrData[$d]) ? $dtrData[$d]['pm_out'] : '';
                        @endphp
                        <button class="edit-btn" onclick="openEditRequest({{ $d }}, '{{ $ai }}', '{{ $ao }}', '{{ $pi }}', '{{ $po }}')" title="Request edit">✎</button>
                    </td>
                @endif
            </tr>
        @endfor
    </tbody>
</table>

@php
    $totalHoursFormatted = sprintf('%02d:%02d', floor($totalMinutes / 60), $totalMinutes % 60);
    $totalLateFormatted = sprintf('%02d:%02d', floor($totalLate / 60), $totalLate % 60);
    $totalUndertimeFormatted = sprintf('%02d:%02d', floor($totalUndertime / 60), $totalUndertime % 60);
@endphp

<div class="dtr-summary">
    <div class="sum-left">
        <strong>TOTAL</strong> &mdash; Days Present: {{ $presentWeekdays }} / {{ $totalWeekdays }}
        &nbsp;|&nbsp; Hours: <strong>{{ $totalHoursFormatted }}</strong>
    </div>
    <div class="sum-right">
        @if ($totalLate > 0) Late: {{ $totalLateFormatted }} @endif
        @if ($totalUndertime > 0){{ $totalLate > 0 ? ' | ' : '' }} UT: {{ $totalUndertimeFormatted }} @endif
        &nbsp;|&nbsp;
        AM {{ $settings['am_start'] ?? '08:00' }}-{{ $settings['am_end'] ?? '12:00' }}
        PM {{ $settings['pm_start'] ?? '13:00' }}-{{ $settings['pm_end'] ?? '17:00' }}
    </div>
</div>


<div style="text-align:left; font-style:italic; font-size:9px; margin:8px 0;">
    I certify on my honor that the above is a true and correct report<br>
    of the hours of work performed, record of which was made daily<br>
    at the time of arrival and departure from office.
</div>
<div style="text-align:center; font-size:10px; margin:24px 0 4px;">
    <strong><u>{{ $employee->full_name }}</u></strong>
</div>
<div style="text-align:left; font-style:italic; font-size:9px; margin-bottom:20px;">VERIFIED as to the prescribed office hours:</div>
@php
    $isEmployeeOfficeSupervisor = \App\Models\Office::where('supervisor_id', $employee->id)->exists();
@endphp
@if ($isEmployeeOfficeSupervisor)
    @php
        $seniorManager = $employee->officeModel && $employee->officeModel->seniorManager ? $employee->officeModel->seniorManager : null;
    @endphp
    @if ($seniorManager)
    <div style="text-align:center; font-size:10px; margin-top:24px;">
        <strong><u>{{ $seniorManager->full_name }}</u></strong><br>
        <span style="font-size:9px;">{{ $seniorManager->position ?: 'Senior Manager' }}</span>
    </div>
    @endif
@else
    @php
        $officeSupervisor = $employee->officeModel && $employee->officeModel->supervisor ? $employee->officeModel->supervisor : null;
    @endphp
    @if ($officeSupervisor)
    <div style="text-align:center; font-size:10px; margin-top:24px;">
        <strong><u>{{ $officeSupervisor->full_name }}</u></strong><br>
        <span style="font-size:9px;">{{ $officeSupervisor->position ?: 'Division Supervisor' }}</span>
    </div>
    @endif
@endif


