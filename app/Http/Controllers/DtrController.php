<?php

namespace App\Http\Controllers;

use App\Models\DtrEditRequest;
use App\Models\DtrUser;
use App\Models\DtrSetting;
use App\Models\IclockTransaction;
use App\Models\Section;
use Illuminate\Http\Request;

class DtrController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $sectionSupervisorIds = [];
        $officeSupervisorIds = [];
        $canViewAll = $user->is_super;

        if ($canViewAll) {
            $employees = DtrUser::where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        } else {
            $ahUserId = DtrSetting::where('setting_key', 'agency_head_user_id')->value('setting_value');

            if ($ahUserId && (int) $ahUserId === $user->id) {
                $osIds = \App\Models\Office::whereNotNull('supervisor_id')->pluck('supervisor_id')->toArray();
                $ahDtr = DtrUser::where('emp_code', $user->emp_code)->first();
                $ahOfficeId = $ahDtr ? $ahDtr->office_id : null;
                $employees = DtrUser::where('is_active', true)
                    ->where(function ($q) use ($osIds, $ahOfficeId) {
                        $q->whereIn('id', $osIds);
                        if ($ahOfficeId) {
                            $q->orWhere('office_id', $ahOfficeId);
                        }
                    })
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get();
                $canViewAll = true;
            } else {
                $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();

                if ($dtrUser) {
                    $sectionSupervisorIds = Section::where('supervisor_id', $dtrUser->id)->pluck('id')->toArray();
                    $officeSupervisorIds = \App\Models\Office::where('supervisor_id', $dtrUser->id)->pluck('id')->toArray();
                    $oicOfficeIds = \App\Models\Office::where('oic_id', $dtrUser->id)->pluck('id')->toArray();
                }

                $employeeQuery = DtrUser::where('is_active', true);

                if (!empty($sectionSupervisorIds) || !empty($officeSupervisorIds) || !empty($oicOfficeIds)) {
                    $employeeQuery->where(function ($q) use ($sectionSupervisorIds, $officeSupervisorIds, $oicOfficeIds) {
                        if (!empty($sectionSupervisorIds)) {
                            $q->whereIn('section_id', $sectionSupervisorIds);
                        }
                        if (!empty($officeSupervisorIds)) {
                            $q->orWhereIn('office_id', $officeSupervisorIds);
                        }
                        if (!empty($oicOfficeIds)) {
                            $q->orWhereIn('office_id', $oicOfficeIds);
                        }
                    });
                    $canViewAll = true;
                } else {
                    $employeeQuery->where('emp_code', $user->emp_code);
                }

                $employees = $employeeQuery->orderBy('first_name')->orderBy('last_name')->get();
            }
        }

        $dtrData = null;
        $month = null;
        $year = null;
        $monthName = null;
        $daysInMonth = null;
        $presentDays = null;
        $totalMinutes = null;
        $totalLate = null;
        $totalUndertime = null;
        $employee = null;

        if ($request->has('month') && $request->has('year')) {
            $empCode = $request->emp;
            if (!$empCode && !$canViewAll) {
                $empCode = $user->emp_code;
            }
            if ($empCode) {
                $month = (int) $request->month;
                $year = (int) $request->year;

                $employee = DtrUser::where('emp_code', $empCode)
                    ->where('is_active', true)
                    ->first();

                if ($employee) {
                    $settings = DtrSetting::getSettings();
                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                    $monthName = date('F', mktime(0, 0, 0, $month, 1));

                    $dtrData = $this->computeDtr($empCode, $year, $month, $settings);

                    $approvedEdits = DtrEditRequest::with('employee')
                        ->forEmployee($empCode)
                        ->forPeriod($year, $month)
                        ->approved()
                        ->get();

                    foreach ($approvedEdits as $edit) {
                        $dayNum = (int) $edit->target_date->format('j');
                        if (!isset($dtrData[$dayNum])) {
                            $dtrData[$dayNum] = [
                                'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                                'total_hours' => '', 'remarks' => '', 'has_punch' => false,
                            ];
                        }
                        $dtrData[$dayNum]['is_edited'] = true;

                        switch ($edit->type) {
                            case 'time_correction':
                                $dtrData[$dayNum][$edit->field] = $edit->new_value;
                                $dtrData[$dayNum]['has_punch'] = true;
                                break;
                            case 'absent':
                                $dtrData[$dayNum]['am_in'] = '';
                                $dtrData[$dayNum]['am_out'] = '';
                                $dtrData[$dayNum]['pm_in'] = '';
                                $dtrData[$dayNum]['pm_out'] = '';
                                $dtrData[$dayNum]['total_hours'] = '';
                                $dtrData[$dayNum]['remarks'] = 'Absent';
                                $dtrData[$dayNum]['has_punch'] = true;
                                break;
                            case 'halfday_am':
                                $dtrData[$dayNum]['am_in'] = '';
                                $dtrData[$dayNum]['am_out'] = '';
                                if ($edit->field === 'am_out' && $edit->new_value) {
                                    $dtrData[$dayNum]['am_out'] = $edit->new_value;
                                }
                                $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                                $dtrData[$dayNum]['has_punch'] = true;
                                break;
                            case 'halfday_pm':
                                $dtrData[$dayNum]['pm_in'] = '';
                                $dtrData[$dayNum]['pm_out'] = '';
                                if ($edit->field === 'pm_in' && $edit->new_value) {
                                    $dtrData[$dayNum]['pm_in'] = $edit->new_value;
                                }
                                $dtrData[$dayNum]['remarks'] = 'Halfday (PM)';
                                $dtrData[$dayNum]['has_punch'] = true;
                                break;
                            case 'holiday':
                                $dtrData[$dayNum]['remarks'] = 'Holiday';
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['is_holiday'] = true;
                                break;
                            case 'wfh':
                                $dtrData[$dayNum]['remarks'] = 'WFH';
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['is_wfh'] = true;
                                break;
                            case 'special_order':
                                $dtrData[$dayNum]['remarks'] = 'Special Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['so_number'] = $edit->new_value;
                                break;
                            case 'travel_order':
                                $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['to_number'] = $edit->new_value;
                                break;
                        }
                    }

                    foreach ($approvedEdits as $edit) {
                        if (!in_array($edit->type, ['time_correction', 'halfday_am', 'halfday_pm'])) continue;
                        $dayNum = (int) $edit->target_date->format('j');
                        $day = $dtrData[$dayNum];
                        if ($edit->type === 'time_correction') {
                            $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                            $dtrData[$dayNum]['remarks'] = $this->recalcRemarks($day, $settings);
                        } else {
                            $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                        }
                    }

                    $presentDays = 0;
                    $totalMinutes = 0;
                    $totalLate = 0;
                    $totalUndertime = 0;

                    foreach ($dtrData as $dayNum => $day) {
                        $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
                        if ($day['has_punch'] && $dow <= 5) {
                            $presentDays++;
                            if ($day['total_hours']) {
                                $parts = explode(':', $day['total_hours']);
                                $totalMinutes += (int) $parts[0] * 60 + (int) $parts[1];
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
                }
            }
        }

        $settings = $dtrData ? DtrSetting::getSettings() : [];
        $isOwnDtr = $employee && isset($empCode) && $empCode === $user->emp_code;

        return view('dtr.index', compact(
            'employees', 'dtrData', 'month', 'year', 'monthName',
            'daysInMonth', 'presentDays', 'totalMinutes', 'totalLate',
            'totalUndertime', 'employee', 'settings', 'isOwnDtr'
        ));
    }

    public function show(Request $request)
    {
        $user = auth()->user();

        $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
        $sectionSupervisorIds = $dtrUser
            ? Section::where('supervisor_id', $dtrUser->id)->pluck('id')->toArray()
            : [];
        $officeSupervisorIds = $dtrUser
            ? \App\Models\Office::where('supervisor_id', $dtrUser->id)->pluck('id')->toArray()
            : [];
        $oicOfficeIds = $dtrUser
            ? \App\Models\Office::where('oic_id', $dtrUser->id)->pluck('id')->toArray()
            : [];
        $canViewAll = $user->is_super || !empty($sectionSupervisorIds) || !empty($officeSupervisorIds) || !empty($oicOfficeIds);

        if (!$canViewAll) {
            $ahUserId = DtrSetting::where('setting_key', 'agency_head_user_id')->value('setting_value');
            if ($ahUserId && (int) $ahUserId === $user->id) {
                $canViewAll = true;
            }
        }

        if ($canViewAll) {
            $request->validate([
                'emp' => 'required|string',
            ]);
            $empCode = $request->emp;
        } else {
            $empCode = $user->emp_code;
        }

        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2000,2100',
        ]);
        $month = (int) $request->month;
        $year = (int) $request->year;

        $employee = DtrUser::where('emp_code', $empCode)
            ->where('is_active', true)
            ->firstOrFail();

        $ahUserId = DtrSetting::where('setting_key', 'agency_head_user_id')->value('setting_value');
        if (!$user->is_super && $ahUserId && (int) $ahUserId === $user->id) {
            $osIds = \App\Models\Office::whereNotNull('supervisor_id')->pluck('supervisor_id')->toArray();
            $ahDtr = DtrUser::where('emp_code', $user->emp_code)->first();
            $ahOfficeId = $ahDtr ? $ahDtr->office_id : null;
            $allowed = in_array($employee->id, $osIds) || ($ahOfficeId && $employee->office_id == $ahOfficeId);
            if (!$allowed) {
                abort(403);
            }
        }

        $settings = DtrSetting::getSettings();
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        $dtrData = $this->computeDtr($empCode, $year, $month, $settings);

        $approvedEdits = DtrEditRequest::with('employee')
            ->forEmployee($empCode)
            ->forPeriod($year, $month)
            ->approved()
            ->get();

        foreach ($approvedEdits as $edit) {
            $dayNum = (int) $edit->target_date->format('j');
            if (!isset($dtrData[$dayNum])) {
                $dtrData[$dayNum] = [
                    'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                    'total_hours' => '', 'remarks' => '', 'has_punch' => false,
                ];
            }
            $dtrData[$dayNum]['is_edited'] = true;

            switch ($edit->type) {
                case 'time_correction':
                    $dtrData[$dayNum][$edit->field] = $edit->new_value;
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'absent':
                    $dtrData[$dayNum]['am_in'] = '';
                    $dtrData[$dayNum]['am_out'] = '';
                    $dtrData[$dayNum]['pm_in'] = '';
                    $dtrData[$dayNum]['pm_out'] = '';
                    $dtrData[$dayNum]['total_hours'] = '';
                    $dtrData[$dayNum]['remarks'] = 'Absent';
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'halfday_am':
                    $dtrData[$dayNum]['am_in'] = '';
                    $dtrData[$dayNum]['am_out'] = '';
                    if ($edit->field === 'am_out' && $edit->new_value) {
                        $dtrData[$dayNum]['am_out'] = $edit->new_value;
                    }
                    $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'halfday_pm':
                    $dtrData[$dayNum]['pm_in'] = '';
                    $dtrData[$dayNum]['pm_out'] = '';
                    if ($edit->field === 'pm_in' && $edit->new_value) {
                        $dtrData[$dayNum]['pm_in'] = $edit->new_value;
                    }
                    $dtrData[$dayNum]['remarks'] = 'Halfday (PM)';
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'holiday':
                    $dtrData[$dayNum]['remarks'] = 'Holiday';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['is_holiday'] = true;
                    break;
                case 'wfh':
                    $dtrData[$dayNum]['remarks'] = 'WFH';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['is_wfh'] = true;
                    break;
                case 'special_order':
                    $dtrData[$dayNum]['remarks'] = 'Special Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['so_number'] = $edit->new_value;
                    break;
                case 'travel_order':
                    $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['to_number'] = $edit->new_value;
                    break;
            }
        }

        foreach ($approvedEdits as $edit) {
            if (!in_array($edit->type, ['time_correction', 'halfday_am', 'halfday_pm'])) continue;
            $dayNum = (int) $edit->target_date->format('j');
            $day = $dtrData[$dayNum];
            if ($edit->type === 'time_correction') {
                $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                $dtrData[$dayNum]['remarks'] = $this->recalcRemarks($day, $settings);
            } else {
                $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
            }
        }

        $presentDays = 0;
        $totalMinutes = 0;
        $totalLate = 0;
        $totalUndertime = 0;

        foreach ($dtrData as $dayNum => $day) {
            $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
            if ($day['has_punch'] && $dow <= 5) {
                $presentDays++;
                if ($day['total_hours']) {
                    $parts = explode(':', $day['total_hours']);
                    $totalMinutes += (int) $parts[0] * 60 + (int) $parts[1];
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

        $isOwnDtr = $empCode === $user->emp_code;
        $isSupervisor = false;
        if (!$user->is_super) {
            if ($dtrUser) {
                $isSupervisor = Section::where('supervisor_id', $dtrUser->id)
                    ->whereHas('dtrUsers', function ($q) use ($employee) {
                        $q->where('id', $employee->id);
                    })->exists()
                    || \App\Models\Office::where('supervisor_id', $dtrUser->id)
                        ->whereHas('dtrUsers', function ($q) use ($employee) {
                            $q->where('id', $employee->id);
                        })->exists()
                    || \App\Models\Office::where('oic_id', $dtrUser->id)
                        ->whereHas('dtrUsers', function ($q) use ($employee) {
                            $q->where('id', $employee->id);
                        })->exists();
            }
        }

        $pendingRequests = DtrEditRequest::with('employee')
            ->forEmployee($empCode)
            ->forPeriod($year, $month)
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();

        $approvedRequests = DtrEditRequest::with('employee')
            ->forEmployee($empCode)
            ->forPeriod($year, $month)
            ->approved()
            ->orderBy('target_date', 'asc')
            ->get();

        return view('dtr.show', compact(
            'employee', 'settings', 'dtrData', 'month', 'year',
            'daysInMonth', 'monthName', 'presentDays',
            'totalMinutes', 'totalLate', 'totalUndertime',
            'isOwnDtr', 'isSupervisor', 'pendingRequests', 'approvedRequests'
        ));
    }

    public function printAll(Request $request)
    {
        $user = auth()->user();
        if (!$user->is_super) {
            abort(403);
        }

        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2000,2100',
        ]);
        $month = (int) $request->month;
        $year = (int) $request->year;

        $employees = DtrUser::where('is_active', true)
            ->orderBy('office')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $settings = DtrSetting::getSettings();
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        $allDtrs = [];
        foreach ($employees as $employee) {
            $dtrData = $this->computeDtr($employee->emp_code, $year, $month, $settings);

            $approvedEdits = DtrEditRequest::with('employee')
                ->forEmployee($employee->emp_code)
                ->forPeriod($year, $month)
                ->approved()
                ->get();

            foreach ($approvedEdits as $edit) {
                $dayNum = (int) $edit->target_date->format('j');
                if (!isset($dtrData[$dayNum])) {
                    $dtrData[$dayNum] = [
                        'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                        'total_hours' => '', 'remarks' => '', 'has_punch' => false,
                    ];
                }
                $dtrData[$dayNum]['is_edited'] = true;

                switch ($edit->type) {
                    case 'time_correction':
                        $dtrData[$dayNum][$edit->field] = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'absent':
                        $dtrData[$dayNum]['am_in'] = '';
                        $dtrData[$dayNum]['am_out'] = '';
                        $dtrData[$dayNum]['pm_in'] = '';
                        $dtrData[$dayNum]['pm_out'] = '';
                        $dtrData[$dayNum]['total_hours'] = '';
                        $dtrData[$dayNum]['remarks'] = 'Absent';
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'halfday_am':
                        $dtrData[$dayNum]['am_in'] = '';
                        $dtrData[$dayNum]['am_out'] = '';
                        if ($edit->field === 'am_out' && $edit->new_value) {
                            $dtrData[$dayNum]['am_out'] = $edit->new_value;
                        }
                        $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'halfday_pm':
                        $dtrData[$dayNum]['pm_in'] = '';
                        $dtrData[$dayNum]['pm_out'] = '';
                        if ($edit->field === 'pm_in' && $edit->new_value) {
                            $dtrData[$dayNum]['pm_in'] = $edit->new_value;
                        }
                        $dtrData[$dayNum]['remarks'] = 'Halfday (PM)';
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'holiday':
                        $dtrData[$dayNum]['remarks'] = 'Holiday';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['is_holiday'] = true;
                        break;
                    case 'wfh':
                        $dtrData[$dayNum]['remarks'] = 'WFH';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['is_wfh'] = true;
                        break;
                    case 'special_order':
                        $dtrData[$dayNum]['remarks'] = 'Special Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['so_number'] = $edit->new_value;
                        break;
                    case 'travel_order':
                        $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['to_number'] = $edit->new_value;
                        break;
                }
            }

            foreach ($approvedEdits as $edit) {
                if (!in_array($edit->type, ['time_correction', 'halfday_am', 'halfday_pm'])) continue;
                $dayNum = (int) $edit->target_date->format('j');
                $day = $dtrData[$dayNum];
                if ($edit->type === 'time_correction') {
                    $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                    $dtrData[$dayNum]['remarks'] = $this->recalcRemarks($day, $settings);
                } else {
                    $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                }
            }

            $allDtrs[] = [
                'employee' => $employee,
                'dtrData' => $dtrData,
            ];
        }

        return view('dtr.print-all', compact('allDtrs', 'month', 'year', 'monthName', 'daysInMonth', 'settings'));
    }

    private function recalcHours($day, $settings)
    {
        $amIn = $day['am_in'] ?? '';
        $amOut = $day['am_out'] ?? '';
        $pmIn = $day['pm_in'] ?? '';
        $pmOut = $day['pm_out'] ?? '';

        $hasAm = $amIn || $amOut;
        $hasPm = $pmIn || $pmOut;

        if (!$hasAm && !$hasPm) return '';

        if ($amIn && $pmOut) {
            $amInTS = strtotime($amIn);
            $pmOutTS = strtotime($pmOut);
            $totalMinutesWorked = ($pmOutTS - $amInTS) / 60;
            $lunchBreak = 60;
            if ($amOut && $pmIn) {
                $amOutTS = strtotime($amOut);
                $pmInTS = strtotime($pmIn);
                if ($pmInTS > $amOutTS) {
                    $lunchBreak = ($pmInTS - $amOutTS) / 60;
                    if ($lunchBreak > 180) $lunchBreak = 60;
                }
            }
            $totalMin = $totalMinutesWorked - $lunchBreak;
            if ($totalMin < 0) $totalMin = 0;
            $hours = floor($totalMin / 60);
            $mins = round($totalMin % 60);
            return sprintf('%02d:%02d', $hours, $mins);
        }

        if ($pmIn && $pmOut) {
            $pmInTS = strtotime($pmIn);
            $pmOutTS = strtotime($pmOut);
            $totalMin = ($pmOutTS - $pmInTS) / 60;
            if ($totalMin < 0) $totalMin = 0;
            $hours = floor($totalMin / 60);
            $mins = round($totalMin % 60);
            return sprintf('%02d:%02d', $hours, $mins);
        }

        if ($amIn && $amOut) {
            $amInTS = strtotime($amIn);
            $amOutTS = strtotime($amOut);
            $totalMin = ($amOutTS - $amInTS) / 60;
            if ($totalMin < 0) $totalMin = 0;
            $hours = floor($totalMin / 60);
            $mins = round($totalMin % 60);
            return sprintf('%02d:%02d', $hours, $mins);
        }

        return '--:--';
    }

    private function recalcRemarks($day, $settings)
    {
        $amIn = $day['am_in'] ?? '';
        $amOut = $day['am_out'] ?? '';
        $pmIn = $day['pm_in'] ?? '';
        $pmOut = $day['pm_out'] ?? '';

        $settingsAmStart = $settings['am_start'] ?? '08:00';
        $settingsPmStart = $settings['pm_start'] ?? '13:00';

        $remarks = [];

        $lateAM = 0;
        if ($amIn) {
            $amLateThreshold = strtotime('09:00');
            $amInTS = strtotime($amIn);
            if ($amInTS > $amLateThreshold) {
                $lateAM = ($amInTS - $amLateThreshold) / 60;
            }
        }
        $latePM = 0;
        if ($pmIn) {
            $pmStartTS = strtotime($settingsPmStart);
            $pmInTS = strtotime($pmIn);
            if ($pmInTS > $pmStartTS) {
                $latePM = ($pmInTS - $pmStartTS) / 60;
            }
        }
        if ($lateAM > 0 || $latePM > 0) {
            $parts = [];
            if ($lateAM > 0) $parts[] = gmdate('H:i', $lateAM * 60);
            if ($latePM > 0) $parts[] = gmdate('H:i', $latePM * 60);
            $remarks[] = 'Late: ' . implode('+', $parts);
        }

        $settingsPmEnd = $settings['pm_end'] ?? '17:00';
        $undertimeMinutes = 0;
        if ($pmIn && preg_match('/^\d/', $pmIn) && $pmOut) {
            $pmEndTS = strtotime($settingsPmEnd);
            $pmOutTS = strtotime($pmOut);
            if ($pmOutTS < $pmEndTS) {
                $undertimeMinutes = ($pmEndTS - $pmOutTS) / 60;
            }
        }
        if ($undertimeMinutes > 0) {
            $remarks[] = 'UT: ' . gmdate('H:i', $undertimeMinutes * 60);
        }

        return implode(' | ', $remarks);
    }

    private function computeDtr($empCode, $year, $month, $settings)
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $firstDay = "$year-$month-01";
        $lastDay = "$year-$month-$daysInMonth";

        $punches = IclockTransaction::forEmployee($empCode)
            ->forPeriod($firstDay, $lastDay)
            ->ordered()
            ->get();

        $settingsAmStart = $settings['am_start'] ?? '08:00';
        $settingsAmEnd = $settings['am_end'] ?? '12:00';
        $settingsPmStart = $settings['pm_start'] ?? '13:00';
        $settingsPmEnd = $settings['pm_end'] ?? '17:00';

        $seen = [];
        $daily = [];
        foreach ($punches as $p) {
            $key = $p->punch_time->format('Y-m-d H:i') . '|' . $p->punch_state;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $date = $p->punch_time->format('Y-m-d');
            $daily[$date][] = $p;
        }

        $dtr = [];
        foreach ($daily as $date => $dayPunches) {
            $dayNum = (int) date('j', strtotime($date));
            $dayOfWeek = date('l', strtotime($date));

            $amStart = strtotime($date . ' ' . $settingsAmStart);
            $amEnd = strtotime($date . ' ' . $settingsAmEnd);
            $pmStart = strtotime($date . ' ' . $settingsPmStart);
            $pmEnd = strtotime($date . ' ' . $settingsPmEnd);

            $amIn = null;
            $amOut = null;
            $pmIn = null;
            $pmOut = null;

            foreach ($dayPunches as $p) {
                $t = $p->punch_time->timestamp;
                $state = (int) $p->punch_state;

                $isIn = $state === 0 || $state === 5;
                $isOut = $state === 1 || $state === 4;

                if ($isIn && $t <= $amEnd) {
                    if ($amIn === null || $t < $amIn) $amIn = $t;
                } elseif ($isOut && $t <= $amEnd) {
                    if ($amOut === null || $t > $amOut) $amOut = $t;
                } elseif ($isIn && $t > $amEnd) {
                    if ($pmIn === null || $t < $pmIn) $pmIn = $t;
                } elseif ($isOut && $t > $amEnd) {
                    if ($pmOut === null || $t > $pmOut) $pmOut = $t;
                }
            }

            $remarks = [];

            $lateAM = 0;
            $amLateThreshold = strtotime($date . ' 09:00');
            if ($amIn && $amIn > $amLateThreshold) {
                $lateAM = ($amIn - $amLateThreshold) / 60;
            }
            $latePM = 0;
            if ($pmIn && $pmIn > $pmStart) {
                $latePM = ($pmIn - $pmStart) / 60;
            }
            if ($lateAM > 0 || $latePM > 0) {
                $parts = [];
                if ($lateAM > 0) $parts[] = gmdate('H:i', $lateAM * 60);
                if ($latePM > 0) $parts[] = gmdate('H:i', $latePM * 60);
                $remarks[] = 'Late: ' . implode('+', $parts);
            }

            $undertimeMinutes = 0;
            if ($pmOut && $pmOut < $pmEnd) {
                $undertimeMinutes = ($pmEnd - $pmOut) / 60;
            }
            if ($undertimeMinutes > 0) {
                $remarks[] = 'UT: ' . gmdate('H:i', $undertimeMinutes * 60);
            }

            $totalHours = 0;
            if ($amIn && $pmOut) {
                $totalMinutesWorked = ($pmOut - $amIn) / 60;
                $lunchBreak = 60;
                if ($amOut && $pmIn && $pmIn > $amOut) {
                    $lunchBreak = ($pmIn - $amOut) / 60;
                    if ($lunchBreak > 180) $lunchBreak = 60;
                }
                $totalMin = $totalMinutesWorked - $lunchBreak;
                if ($totalMin < 0) $totalMin = 0;
                $hours = floor($totalMin / 60);
                $mins = round($totalMin % 60);
                $totalHours = sprintf('%02d:%02d', $hours, $mins);
            } elseif ($amIn || $pmOut) {
                $totalHours = '--:--';
            }

            $dtr[$dayNum] = [
                'am_in' => $amIn ? date('H:i', $amIn) : '',
                'am_out' => $amOut ? date('H:i', $amOut) : '',
                'pm_in' => $pmIn ? date('H:i', $pmIn) : '',
                'pm_out' => $pmOut ? date('H:i', $pmOut) : '',
                'total_hours' => $totalHours,
                'remarks' => implode(' | ', $remarks),
                'has_punch' => $amIn !== null || $amOut !== null || $pmIn !== null || $pmOut !== null,
            ];
        }

        return $dtr;
    }
}
