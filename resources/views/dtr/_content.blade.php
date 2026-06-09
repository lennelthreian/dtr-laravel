<div style="font-family:Arial, sans-serif; font-style:italic; font-size:11px; margin-bottom:6px; text-align:left;">Civil Service Form No. 48</div>
<table class="dtr-header-table">
    <tr>
        <td colspan="2" style="text-align:center;">
            <h1 style="font-size:16px; letter-spacing:1px; margin:0 0 6px; border-bottom:1px solid #000; display:inline-block; padding-bottom:3px;">DAILY TIME RECORD</h1>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="text-align:center; padding:4px 0;">
            <h1 style="font-size:17px; margin:0; text-decoration:underline; font-weight:700;">{{ $employee->full_name }}</h1>
            <div style="font-size:10px; margin-top:2px; color:var(--gray-600);">(Name)</div>
        </td>
    </tr>
    <tr>
        <td colspan="2" class="dtr-header-right" style="text-align:left;">
            <h2 style="font-size:14px; margin:4px 0 0; text-align:center; font-weight:400;">For the month of <u>{{ $monthName }} {{ $year }}</u></h2>
        </td>
    </tr>
</table>

@php
    $empMaxDow = $employee->default_work_week === '4-day' ? 4 : (($settings['four_day_work_week'] ?? '0') === '1' ? 4 : ($settings['max_dow'] ?? 5));
    $totalWeekdays = 0; $presentWeekdays = 0; $totalSaturdays = 0; $presentSaturdays = 0;
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d)));
        $dayMaxDow = (isset($dtrData[$d]['work_week_type']) ? ($dtrData[$d]['work_week_type'] === '4-day' ? 4 : 5) : $empMaxDow);
        $isExcluded = isset($dtrData[$d]) && (!empty($dtrData[$d]['is_holiday']) || !empty($dtrData[$d]['is_work_suspension']));
        if ($dow <= $dayMaxDow && !$isExcluded) { $totalWeekdays++; if (isset($dtrData[$d]) && $dtrData[$d]['has_punch'] && !$isExcluded) $presentWeekdays++; }
        if ($dow == 6 && !$isExcluded) { $totalSaturdays++; if (isset($dtrData[$d]) && $dtrData[$d]['has_punch'] && !$isExcluded) $presentSaturdays++; }
    }
@endphp
<div style="display:flex; justify-content:space-between; font-size:11px; margin:6px 0; font-style:italic;">
    <div>Official hours for<br>arrival and departure</div>
    <div style="text-align:right;">Regular days ({{ $totalWeekdays }})<br>Saturdays ({{ $totalSaturdays }})</div>
</div>


<table class="dtr-table">
    <colgroup>
        <col>
        <col>
        <col>
        <col>
        <col>
        <col>
        <col>
    </colgroup>
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
                $dowN = date('N', strtotime($dateStr));
                $isNonWorkingDay = $dowN > $empMaxDow || in_array($dayOfWeek, ['Saturday', 'Sunday']);
                $hasData = isset($dtrData[$d]) && $dtrData[$d]['has_punch'];
            @endphp
            @php
                $editedFields = $dtrData[$d]['edited_fields'] ?? [];
                $rowClass = trim(($isNonWorkingDay && !$hasData ? 'weekend ' : '') . ($hasData ? 'has-data ' : ''));
            @endphp
            @php
                $dayWW = $dtrData[$d]['work_week_type'] ?? $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');
            @endphp
            <tr class="{{ $rowClass }}">
                <td class="day-col">
                    {{ $d }}
                    <span class="dow">{{ substr($dayOfWeek, 0, 3) }}</span>
                    @if (isset($isOwnDtr) && $isOwnDtr)
                        <span class="ww-badge ww-clickable no-print" onclick="toggleDayWorkWeek('{{ $dateStr }}', '{{ $dayWW === '4-day' ? '5-day' : '4-day' }}')">{{ $dayWW === '4-day' ? '4d' : '5d' }}</span>
                    @elseif (isset($dtrData[$d]['work_week_type']))
                        <span class="ww-badge no-print">{{ $dayWW === '4-day' ? '4d' : '5d' }}</span>
                    @endif
                </td>
                @if (!empty($dtrData[$d]['so_number']) && strpos($dtrData[$d]['remarks'] ?? '', '(AM)') === false && strpos($dtrData[$d]['remarks'] ?? '', '(PM)') === false)
                    <td class="time-col has-val" colspan="4" style="text-align:center;">SO: {{ $dtrData[$d]['so_number'] }}</td>
                    <td class="hours-col has-val">{{ $dtrData[$d]['total_hours'] }}</td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif (!empty($dtrData[$d]['to_number']) && strpos($dtrData[$d]['remarks'] ?? '', '(AM)') === false && strpos($dtrData[$d]['remarks'] ?? '', '(PM)') === false)
                    <td class="time-col has-val" colspan="4" style="text-align:center;">TO: {{ $dtrData[$d]['to_number'] }}</td>
                    <td class="hours-col has-val">{{ $dtrData[$d]['total_hours'] }}</td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif (!empty($dtrData[$d]['ob_number']) && strpos($dtrData[$d]['remarks'] ?? '', '(AM)') === false && strpos($dtrData[$d]['remarks'] ?? '', '(PM)') === false)
                    <td class="time-col has-val" colspan="4" style="text-align:center;">OB: {{ $dtrData[$d]['ob_number'] }}</td>
                    <td class="hours-col has-val">{{ $dtrData[$d]['total_hours'] }}</td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif (!empty($dtrData[$d]['is_wfh']))
                    <td class="time-col has-val" colspan="4" style="text-align:center;">WFH</td>
                    <td class="hours-col has-val">{{ $dtrData[$d]['total_hours'] }}</td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif (isset($dtrData[$d]['am_in'], $dtrData[$d]['pm_in']) && $dtrData[$d]['am_in'] === 'ON LEAVE' && $dtrData[$d]['pm_in'] === 'ON LEAVE')
                    <td class="time-col has-val" colspan="4" style="text-align:center;font-weight:600;color:#166534;">ON LEAVE</td>
                    <td class="hours-col has-val">{{ $dtrData[$d]['total_hours'] }}</td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif (!empty($dtrData[$d]['is_holiday']))
                    <td class="time-col has-val" colspan="4" style="text-align:center;">Holiday</td>
                    <td class="hours-col"></td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif (!empty($dtrData[$d]['is_work_suspension']))
                    <td class="time-col has-val" colspan="4" style="text-align:center;">Work Suspension</td>
                    <td class="hours-col"></td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @elseif ($hasData)
                    @php
                        $ai = $dtrData[$d]['am_in']; $ao = $dtrData[$d]['am_out'];
                        $pi = $dtrData[$d]['pm_in']; $po = $dtrData[$d]['pm_out'];
                        $amAbsent = ($ai ?? '') === 'ABSENT' && ($ao ?? '') === 'ABSENT';
                        $pmAbsent = ($pi ?? '') === 'ABSENT' && ($po ?? '') === 'ABSENT';
                        $amWfh = ($ai ?? '') === 'WFH';
                        $pmWfh = ($pi ?? '') === 'WFH';
                        $amSo = strpos(($ai ?? ''), 'SO:') === 0;
                        $pmSo = strpos(($pi ?? ''), 'SO:') === 0;
                        $amTo = strpos(($ai ?? ''), 'TO:') === 0;
                        $pmTo = strpos(($pi ?? ''), 'TO:') === 0;
                        $amOb = strpos(($ai ?? ''), 'OB:') === 0;
                        $pmOb = strpos(($pi ?? ''), 'OB:') === 0;
                    @endphp
                    @if ($amSo && $pmSo)
                        <td class="time-col edited-val" colspan="4" style="text-align:center;">SO: {{ $dtrData[$d]['so_number'] ?? '' }}</td>
                    @elseif ($amSo)
                        <td class="time-col edited-val" colspan="2" style="text-align:center;">SO: {{ $dtrData[$d]['so_number'] ?? '' }}</td>
                        <td class="time-col{{ empty($pi) ? ' no-entry' : (in_array('pm_in', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $pi }}</td>
                        <td class="time-col{{ empty($po) ? ' no-entry' : (in_array('pm_out', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $po }}</td>
                    @elseif ($pmSo)
                        <td class="time-col{{ empty($ai) ? ' no-entry' : (in_array('am_in', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $ai }}</td>
                        <td class="time-col{{ empty($ao) ? ' no-entry' : (in_array('am_out', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $ao }}</td>
                        <td class="time-col edited-val" colspan="2" style="text-align:center;">SO: {{ $dtrData[$d]['so_number'] ?? '' }}</td>
                    @elseif ($amTo && $pmTo)
                        <td class="time-col edited-val" colspan="4" style="text-align:center;">TO: {{ $dtrData[$d]['to_number'] ?? '' }}</td>
                    @elseif ($amTo)
                        <td class="time-col edited-val" colspan="2" style="text-align:center;">TO: {{ $dtrData[$d]['to_number'] ?? '' }}</td>
                        <td class="time-col{{ empty($pi) ? ' no-entry' : (in_array('pm_in', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $pi }}</td>
                        <td class="time-col{{ empty($po) ? ' no-entry' : (in_array('pm_out', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $po }}</td>
                    @elseif ($pmTo)
                        <td class="time-col{{ empty($ai) ? ' no-entry' : (in_array('am_in', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $ai }}</td>
                        <td class="time-col{{ empty($ao) ? ' no-entry' : (in_array('am_out', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $ao }}</td>
                        <td class="time-col edited-val" colspan="2" style="text-align:center;">TO: {{ $dtrData[$d]['to_number'] ?? '' }}</td>
                    @elseif ($amOb && $pmOb)
                        <td class="time-col edited-val" colspan="4" style="text-align:center;">OB: {{ $dtrData[$d]['ob_number'] ?? '' }}</td>
                    @elseif ($amOb)
                        <td class="time-col edited-val" colspan="2" style="text-align:center;">OB: {{ $dtrData[$d]['ob_number'] ?? '' }}</td>
                        <td class="time-col{{ empty($pi) ? ' no-entry' : (in_array('pm_in', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $pi }}</td>
                        <td class="time-col{{ empty($po) ? ' no-entry' : (in_array('pm_out', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $po }}</td>
                    @elseif ($pmOb)
                        <td class="time-col{{ empty($ai) ? ' no-entry' : (in_array('am_in', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $ai }}</td>
                        <td class="time-col{{ empty($ao) ? ' no-entry' : (in_array('am_out', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $ao }}</td>
                        <td class="time-col edited-val" colspan="2" style="text-align:center;">OB: {{ $dtrData[$d]['ob_number'] ?? '' }}</td>
                    @elseif ($amAbsent && $pmAbsent)
                        <td class="time-col has-val" colspan="4" style="text-align:center;">ABSENT</td>
                    @elseif ($amAbsent)
                        <td class="time-col has-val" colspan="2" style="text-align:center;">ABSENT</td>
                        <td class="time-col{{ empty($pi) ? ' no-entry' : ' has-val' }}">{{ $pi }}</td>
                        <td class="time-col{{ empty($po) ? ' no-entry' : ' has-val' }}">{{ $po }}</td>
                    @elseif ($pmAbsent)
                        <td class="time-col{{ empty($ai) ? ' no-entry' : ' has-val' }}">{{ $ai }}</td>
                        <td class="time-col{{ empty($ao) ? ' no-entry' : ' has-val' }}">{{ $ao }}</td>
                        <td class="time-col has-val" colspan="2" style="text-align:center;">ABSENT</td>
                    @elseif ($amWfh && $pmWfh)
                        <td class="time-col has-val" colspan="4" style="text-align:center;">WFH</td>
                    @elseif ($amWfh)
                        <td class="time-col has-val" colspan="2" style="text-align:center;">WFH</td>
                        <td class="time-col{{ empty($pi) ? ' no-entry' : ' has-val' }}">{{ $pi }}</td>
                        <td class="time-col{{ empty($po) ? ' no-entry' : ' has-val' }}">{{ $po }}</td>
                    @elseif ($pmWfh)
                        <td class="time-col{{ empty($ai) ? ' no-entry' : ' has-val' }}">{{ $ai }}</td>
                        <td class="time-col{{ empty($ao) ? ' no-entry' : ' has-val' }}">{{ $ao }}</td>
                        <td class="time-col has-val" colspan="2" style="text-align:center;">WFH</td>
                    @else
                        <td class="time-col{{ empty($ai) ? ' no-entry' : (in_array('am_in', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $ai }}</td>
                        <td class="time-col{{ empty($ao) ? ' no-entry' : (in_array('am_out', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $ao }}</td>
                        <td class="time-col{{ empty($pi) ? ' no-entry' : (in_array('pm_in', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $pi }}</td>
                        <td class="time-col{{ empty($po) ? ' no-entry' : (in_array('pm_out', $editedFields) ? ' edited-val' : ' has-val') }}">{{ $po }}</td>
                    @endif
                    <td class="hours-col">{{ $dtrData[$d]['total_hours'] }}</td>
                    <td class="remarks-col">{{ $dtrData[$d]['remarks'] }}</td>
                @else
                    <td class="time-col{{ !$isNonWorkingDay ? ' no-entry' : '' }}"></td>
                    <td class="time-col{{ !$isNonWorkingDay ? ' no-entry' : '' }}"></td>
                    <td class="time-col{{ !$isNonWorkingDay ? ' no-entry' : '' }}"></td>
                    <td class="time-col{{ !$isNonWorkingDay ? ' no-entry' : '' }}"></td>
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
    $totalLateHrs = floor($totalLate / 60);
    $totalLateMins = $totalLate % 60;
    $totalLateFormatted = ($totalLateHrs ? "{$totalLateHrs} hr(s) " : '') . "{$totalLateMins} min(s)";
    $totalUtHrs = floor($totalUndertime / 60);
    $totalUtMins = $totalUndertime % 60;
    $totalUndertimeFormatted = ($totalUtHrs ? "{$totalUtHrs} hr(s) " : '') . "{$totalUtMins} min(s)";
@endphp

<table class="dtr-table" style="margin-top:0; border-top:none;">
    <colgroup>
        <col>
        <col>
        <col>
        <col>
        <col>
        <col>
        <col>
    </colgroup>
    <tr class="dtr-summary">
        <td colspan="5">
            <strong>TOTAL</strong> &mdash; Days Present: {{ $presentWeekdays }} / {{ $totalWeekdays }}
            &nbsp;|&nbsp; Hours: <strong>{{ $totalHoursFormatted }}</strong>
        </td>
        <td colspan="2">
            @if ($totalLate > 0) Late: {{ $totalLateFormatted }} @endif
            @if ($totalUndertime > 0){{ $totalLate > 0 ? ' | ' : '' }} UT: {{ $totalUndertimeFormatted }} @endif
        </td>
        @if (isset($isOwnDtr) && $isOwnDtr)
            <td class="no-print"></td>
        @endif
    </tr>
</table>

<div style="text-align:left; font-style:italic; font-size:11px; margin:10px 0;">
    I certify on my honor that the above is a true and correct report<br>
    of the hours of work performed, record of which was made daily<br>
    at the time of arrival and departure from office.
</div>
<div style="text-align:center; font-size:13px; margin:28px 0 6px;">
    <strong><u>{{ $employee->full_name }}</u></strong>
</div>
<div style="text-align:left; font-style:italic; font-size:11px; margin-bottom:24px;">VERIFIED as to the prescribed office hours:</div>
@php
    $isEmployeeOfficeSupervisor = \App\Models\Office::where('supervisor_id', $employee->id)->exists();
@endphp
@if ($isEmployeeOfficeSupervisor)
    @php
        $seniorManager = $employee->officeModel && $employee->officeModel->seniorManager ? $employee->officeModel->seniorManager : null;
    @endphp
    @if ($seniorManager)
    <div style="text-align:center; font-size:12px; margin-top:28px;">
        <strong><u>{{ $seniorManager->full_name }}</u></strong><br>
        <span style="font-size:11px;">{{ $seniorManager->position ?: 'Senior Manager' }}</span>
    </div>
    @endif
@else
    @php
        $officeSupervisor = $employee->officeModel && $employee->officeModel->supervisor ? $employee->officeModel->supervisor : null;
    @endphp
    @if ($officeSupervisor)
    <div style="text-align:center; font-size:12px; margin-top:28px;">
        <strong><u>{{ $officeSupervisor->full_name }}</u></strong><br>
        <span style="font-size:11px;">{{ $officeSupervisor->position ?: 'Division Supervisor' }}</span>
    </div>
    @endif
@endif


