<?php

namespace App\Http\Controllers;

use App\Models\DtrDayOverride;
use App\Models\DtrEditRequest;
use App\Models\DtrUser;
use App\Models\DtrSetting;
use App\Models\GlobalHoliday;
use App\Models\IclockTransaction;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DtrController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $settings = DtrSetting::getSettings();
        $settings = $this->backupOriginalSchedule($settings);
        $settings = $this->applyFourDaySettings($settings);

        $isSupervisor = $this->checkIsSupervisor($user);
        $sectionSupervisorIds = [];
        $officeSupervisorIds = [];
        $canViewAll = $user->is_super;

        if ($canViewAll) {
            $employees = DtrUser::where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        } else {
            $ahUserId = $settings['agency_head_user_id'] ?? null;

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
                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                    $monthName = date('F', mktime(0, 0, 0, $month, 1));

                    $dtrData = $this->computeDtr($empCode, $year, $month, $settings, $employee->default_work_week ?? null);

                    $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');

                    $dtrData = $this->applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW);

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
                                $dtrData[$dayNum]['am_in'] = 'ABSENT';
                                $dtrData[$dayNum]['am_out'] = 'ABSENT';
                                $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                                $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                                $dtrData[$dayNum]['total_hours'] = '';
                                $dtrData[$dayNum]['remarks'] = 'Absent';
                                $dtrData[$dayNum]['has_punch'] = true;
                                break;
                            case 'halfday_am':
                                $dtrData[$dayNum]['am_in'] = 'ABSENT';
                                $dtrData[$dayNum]['am_out'] = 'ABSENT';
                                if ($edit->field === 'am_out' && $edit->new_value) {
                                    $dtrData[$dayNum]['am_out'] = $edit->new_value;
                                }
                                $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                                $dtrData[$dayNum]['has_punch'] = true;
                                break;
                            case 'halfday_pm':
                                $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                                $dtrData[$dayNum]['pm_out'] = 'ABSENT';
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
                                $dtrData[$dayNum]['has_punch'] = true;
                                $wfhType = $edit->new_value ?: 'whole_day';
                                if ($wfhType === 'am') {
                                    $dtrData[$dayNum]['am_in'] = 'WFH';
                                    $dtrData[$dayNum]['am_out'] = 'WFH';
                                    $dtrData[$dayNum]['remarks'] = 'WFH (AM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                } elseif ($wfhType === 'pm') {
                                    $dtrData[$dayNum]['pm_in'] = 'WFH';
                                    $dtrData[$dayNum]['pm_out'] = 'WFH';
                                    $dtrData[$dayNum]['remarks'] = 'WFH (PM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                } else {
                                    $dtrData[$dayNum]['am_in'] = 'WFH';
                                    $dtrData[$dayNum]['am_out'] = 'WFH';
                                    $dtrData[$dayNum]['pm_in'] = 'WFH';
                                    $dtrData[$dayNum]['pm_out'] = 'WFH';
                                    $dtrData[$dayNum]['is_wfh'] = true;
                                    $dtrData[$dayNum]['remarks'] = 'WFH';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                                }
                                break;
                            case 'special_order':
                                $soType = $edit->field ?: 'whole_day';
                                $soNum = $edit->new_value;
                                $dtrData[$dayNum]['has_punch'] = true;
                                if ($soType === 'am') {
                                    $dtrData[$dayNum]['am_in'] = 'SO: ' . $soNum;
                                    $dtrData[$dayNum]['am_out'] = 'SO: ' . $soNum;
                                    $dtrData[$dayNum]['remarks'] = 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (AM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                } elseif ($soType === 'pm') {
                                    $dtrData[$dayNum]['pm_in'] = 'SO: ' . $soNum;
                                    $dtrData[$dayNum]['pm_out'] = 'SO: ' . $soNum;
                                    $dtrData[$dayNum]['remarks'] = 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (PM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                } else {
                                    $dtrData[$dayNum]['remarks'] = 'Special Order' . ($soNum ? ': ' . $soNum : '');
                                    $dtrData[$dayNum]['so_number'] = $soNum;
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                                }
                                break;
                            case 'travel_order':
                                $toType = $edit->field ?: 'whole_day';
                                $toNum = $edit->new_value;
                                $dtrData[$dayNum]['has_punch'] = true;
                                if ($toType === 'am') {
                                    $dtrData[$dayNum]['am_in'] = 'TO: ' . $toNum;
                                    $dtrData[$dayNum]['am_out'] = 'TO: ' . $toNum;
                                    $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (AM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                } elseif ($toType === 'pm') {
                                    $dtrData[$dayNum]['pm_in'] = 'TO: ' . $toNum;
                                    $dtrData[$dayNum]['pm_out'] = 'TO: ' . $toNum;
                                    $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (PM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                } else {
                                    $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($toNum ? ': ' . $toNum : '');
                                    $dtrData[$dayNum]['to_number'] = $toNum;
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                                }
                                break;
                            case 'work_suspension':
                                $wsType = $edit->new_value ?: 'whole_day';
                                if ($wsType === 'am') {
                                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                                    $dtrData[$dayNum]['remarks'] = 'Work Suspension (AM)';
                                } elseif ($wsType === 'pm') {
                                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                                    $dtrData[$dayNum]['remarks'] = 'Work Suspension (PM)';
                                } else {
                                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                                    $dtrData[$dayNum]['total_hours'] = '';
                                    $dtrData[$dayNum]['remarks'] = 'Work Suspension';
                                }
                                $dtrData[$dayNum]['has_punch'] = true;
                                break;
                            case 'locator_slip':
                                $lsType = $edit->field ?: 'official';
                                $whereabouts = $edit->new_value;
                                $typeLabel = $lsType === 'personal' ? 'Personal' : 'Official';
                                $timeLeft = $edit->ls_time_left ? date('h:i A', strtotime($edit->ls_time_left)) : '';
                                $timeReturned = $edit->ls_no_return ? 'No Return' : ($edit->ls_time_returned ? date('h:i A', strtotime($edit->ls_time_returned)) : '');
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['am_in'] = '';
                                $dtrData[$dayNum]['am_out'] = '';
                                $dtrData[$dayNum]['pm_in'] = '';
                                $dtrData[$dayNum]['pm_out'] = '';
                                $details = array_filter([$whereabouts, $timeLeft ? "Left: $timeLeft" : null, $timeReturned ? "Ret: $timeReturned" : null]);
                                $dtrData[$dayNum]['remarks'] = 'LS: ' . implode(' | ', $details) . " ($typeLabel)";
                                $dtrData[$dayNum]['total_hours'] = $edit->ls_no_return ? '04:00' : ($empDefaultWW === '4-day' ? '10:00' : '08:00');
                                break;
                        }

                        if (in_array($edit->type, ['time_correction', 'halfday_am', 'halfday_pm'])) {
                            $day = $dtrData[$dayNum];
                            if ($edit->type === 'time_correction') {
                                $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                                $dtrData[$dayNum]['remarks'] = $this->recalcRemarks($day, $settings);
                            } else {
                                $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                            }
                        }

                        if (in_array($edit->type, ['halfday_am', 'halfday_pm']) && strpos($dtrData[$dayNum]['remarks'] ?? '', 'Halfday') !== false) {
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        }
                    }

                    $dayOverrides = DtrDayOverride::where('employee_id', $employee->id)
                        ->whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
                        ->get();

                    foreach ($dayOverrides as $override) {
                        $dayNum = (int) $override->target_date->format('j');
                        if (!isset($dtrData[$dayNum])) {
                            $dtrData[$dayNum] = ['am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '', 'total_hours' => '', 'remarks' => '', 'has_punch' => false];
                        }
                        $dtrData[$dayNum]['work_week_type'] = $override->work_week_type;
                    }

                    foreach ($dtrData as $dayNum => &$day) {
                        if (!isset($day['work_week_type'])) continue;
                        $schedule = $this->getScheduleForWorkWeek($day['work_week_type'], $settings);

                        $wfhLabel = $this->resolveWfhLabel($day);

                        $day['remarks'] = $this->recalcRemarks($day, $settings, $schedule);

                        if (!empty($day['is_wfh']) || !empty($day['so_number']) || !empty($day['to_number'])) {
                            $day['total_hours'] = $day['work_week_type'] === '4-day' ? '10:00' : '08:00';
                        }
                        if (strpos($day['remarks'] ?? '', 'Halfday') !== false) {
                            $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                        }

                        if ($wfhLabel) {
                            $day['remarks'] = !empty($day['remarks']) ? $wfhLabel . ' | ' . $day['remarks'] : $wfhLabel;
                        }

                        if (strpos($wfhLabel, '(AM)') !== false || strpos($wfhLabel, '(PM)') !== false) {
                            $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                        }
                    }
                    unset($day);

                    $presentDays = 0;
                    $totalMinutes = 0;
                    $totalLate = 0;
                    $totalUndertime = 0;

                    foreach ($dtrData as $dayNum => $day) {
                        $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
                        $dayMaxDow = isset($day['work_week_type']) ? ($day['work_week_type'] === '4-day' ? 4 : 5) : ((isset($employee) && $employee->default_work_week === '4-day') ? 4 : (($settings['four_day_work_week'] ?? '0') === '1' ? 4 : ($settings['max_dow'] ?? 5)));
                        if (!empty($day['has_punch']) && $dow <= $dayMaxDow) {
                            $presentDays++;
                            if (!empty($day['total_hours'])) {
                                $parts = explode(':', $day['total_hours']);
                                $totalMinutes += (int) $parts[0] * 60 + (int) $parts[1];
                            }
                            if (!empty($day['remarks']) && strpos($day['remarks'], 'Late:') !== false) {
                                preg_match_all('/(\d+):(\d+)/', $day['remarks'], $m);
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
                }
            }
        }

        $isOwnDtr = $employee && isset($empCode) && $empCode === $user->emp_code;

        return view('dtr.index', compact(
            'employees', 'dtrData', 'month', 'year', 'monthName',
            'daysInMonth', 'presentDays', 'totalMinutes', 'totalLate',
            'totalUndertime', 'employee', 'settings', 'isOwnDtr', 'isSupervisor'
        ));
    }

    public function show(Request $request)
    {
        $user = auth()->user();
        $settings = DtrSetting::getSettings();
        $settings = $this->backupOriginalSchedule($settings);
        $settings = $this->applyFourDaySettings($settings);

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

        $ahUserId = $settings['agency_head_user_id'] ?? null;
        if (!$canViewAll && $ahUserId && (int) $ahUserId === $user->id) {
            $canViewAll = true;
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

        if (!$user->is_super && $ahUserId && (int) $ahUserId === $user->id) {
            $osIds = \App\Models\Office::whereNotNull('supervisor_id')->pluck('supervisor_id')->toArray();
            $ahDtr = DtrUser::where('emp_code', $user->emp_code)->first();
            $ahOfficeId = $ahDtr ? $ahDtr->office_id : null;
            $allowed = in_array($employee->id, $osIds) || ($ahOfficeId && $employee->office_id == $ahOfficeId);
            if (!$allowed) {
                abort(403);
            }
        }

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        $dtrData = $this->computeDtr($empCode, $year, $month, $settings, $employee->default_work_week ?? null);

        $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');

        $dtrData = $this->applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW);

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
                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                    $dtrData[$dayNum]['total_hours'] = '';
                    $dtrData[$dayNum]['remarks'] = 'Absent';
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'halfday_am':
                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                    if ($edit->field === 'am_out' && $edit->new_value) {
                        $dtrData[$dayNum]['am_out'] = $edit->new_value;
                    }
                    $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'halfday_pm':
                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
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
                    $dtrData[$dayNum]['has_punch'] = true;
                    $wfhType = $edit->new_value ?: 'whole_day';
                    if ($wfhType === 'am') {
                        $dtrData[$dayNum]['am_in'] = 'WFH';
                        $dtrData[$dayNum]['am_out'] = 'WFH';
                        $dtrData[$dayNum]['remarks'] = 'WFH (AM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                    } elseif ($wfhType === 'pm') {
                        $dtrData[$dayNum]['pm_in'] = 'WFH';
                        $dtrData[$dayNum]['pm_out'] = 'WFH';
                        $dtrData[$dayNum]['remarks'] = 'WFH (PM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                    } else {
                        $dtrData[$dayNum]['am_in'] = 'WFH';
                        $dtrData[$dayNum]['am_out'] = 'WFH';
                        $dtrData[$dayNum]['pm_in'] = 'WFH';
                        $dtrData[$dayNum]['pm_out'] = 'WFH';
                        $dtrData[$dayNum]['is_wfh'] = true;
                        $dtrData[$dayNum]['remarks'] = 'WFH';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                    }
                    break;
                case 'special_order':
                    $dtrData[$dayNum]['remarks'] = 'Special Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['so_number'] = $edit->new_value;
                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                    break;
                case 'travel_order':
                    $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['to_number'] = $edit->new_value;
                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                    break;
                case 'work_suspension':
                    $wsType = $edit->new_value ?: 'whole_day';
                    if ($wsType === 'am') {
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        $dtrData[$dayNum]['remarks'] = 'Work Suspension (AM)';
                    } elseif ($wsType === 'pm') {
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        $dtrData[$dayNum]['remarks'] = 'Work Suspension (PM)';
                    } else {
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        $dtrData[$dayNum]['total_hours'] = '';
                        $dtrData[$dayNum]['remarks'] = 'Work Suspension';
                    }
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'locator_slip':
                    $lsType = $edit->field ?: 'official';
                    $whereabouts = $edit->new_value;
                    $typeLabel = $lsType === 'personal' ? 'Personal' : 'Official';
                    $timeLeft = $edit->ls_time_left ? date('h:i A', strtotime($edit->ls_time_left)) : '';
                    $timeReturned = $edit->ls_no_return ? 'No Return' : ($edit->ls_time_returned ? date('h:i A', strtotime($edit->ls_time_returned)) : '');
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['am_in'] = '';
                    $dtrData[$dayNum]['am_out'] = '';
                    $dtrData[$dayNum]['pm_in'] = '';
                    $dtrData[$dayNum]['pm_out'] = '';
                    $details = array_filter([$whereabouts, $timeLeft ? "Left: $timeLeft" : null, $timeReturned ? "Ret: $timeReturned" : null]);
                    $dtrData[$dayNum]['remarks'] = 'LS: ' . implode(' | ', $details) . " ($typeLabel)";
                    $dtrData[$dayNum]['total_hours'] = $edit->ls_no_return ? '04:00' : ($empDefaultWW === '4-day' ? '10:00' : '08:00');
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

        foreach ($approvedEdits as $edit) {
            if (!in_array($edit->type, ['halfday_am', 'halfday_pm'])) continue;
            $dayNum = (int) $edit->target_date->format('j');
            if (strpos($dtrData[$dayNum]['remarks'] ?? '', 'Halfday') !== false) {
                $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
            }
        }

        $dayOverrides = DtrDayOverride::where('employee_id', $employee->id)
            ->whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->get();

        foreach ($dayOverrides as $override) {
            $dayNum = (int) $override->target_date->format('j');
            if (!isset($dtrData[$dayNum])) {
                $dtrData[$dayNum] = ['am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '', 'total_hours' => '', 'remarks' => '', 'has_punch' => false];
            }
            $dtrData[$dayNum]['work_week_type'] = $override->work_week_type;
        }

        foreach ($dtrData as $dayNum => &$day) {
            if (!isset($day['work_week_type'])) continue;
            $schedule = $this->getScheduleForWorkWeek($day['work_week_type'], $settings);

            $wfhLabel = $this->resolveWfhLabel($day);

            $day['remarks'] = $this->recalcRemarks($day, $settings, $schedule);

            if (!empty($day['is_wfh']) || !empty($day['so_number']) || !empty($day['to_number'])) {
                $day['total_hours'] = $day['work_week_type'] === '4-day' ? '10:00' : '08:00';
            }
            if (strpos($day['remarks'] ?? '', 'Halfday') !== false) {
                $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
            }

            if ($wfhLabel) {
                $day['remarks'] = !empty($day['remarks']) ? $wfhLabel . ' | ' . $day['remarks'] : $wfhLabel;
            }

            if (strpos($wfhLabel, '(AM)') !== false || strpos($wfhLabel, '(PM)') !== false) {
                $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
            }
        }
        unset($day);

        $presentDays = 0;
        $totalMinutes = 0;
        $totalLate = 0;
        $totalUndertime = 0;

        foreach ($dtrData as $dayNum => $day) {
            $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
            $dayMaxDow = isset($day['work_week_type']) ? ($day['work_week_type'] === '4-day' ? 4 : 5) : ((isset($employee) && $employee->default_work_week === '4-day') ? 4 : (($settings['four_day_work_week'] ?? '0') === '1' ? 4 : ($settings['max_dow'] ?? 5)));
            if (!empty($day['has_punch']) && $dow <= $dayMaxDow) {
                $presentDays++;
                if (!empty($day['total_hours'])) {
                    $parts = explode(':', $day['total_hours']);
                    $totalMinutes += (int) $parts[0] * 60 + (int) $parts[1];
                }
                if (!empty($day['remarks']) && strpos($day['remarks'], 'Late:') !== false) {
                    preg_match_all('/(\d+):(\d+)/', $day['remarks'], $m);
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

        $isOwnDtr = $empCode === $user->emp_code;
        $isSupervisor = $user->is_super;
        if (!$isSupervisor && $dtrUser) {
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

        $allEmpRequests = DtrEditRequest::with('employee')
            ->forEmployee($empCode)
            ->forPeriod($year, $month)
            ->get();

        $pendingRequests = $allEmpRequests->where('status', 'pending')->sortByDesc('created_at');
        $approvedRequests = $allEmpRequests->where('status', 'approved')->sortBy('target_date');

        return view('dtr.show', compact(
            'employee', 'settings', 'dtrData', 'month', 'year',
            'daysInMonth', 'monthName', 'presentDays',
            'totalMinutes', 'totalLate', 'totalUndertime',
            'isOwnDtr', 'isSupervisor', 'pendingRequests', 'approvedRequests'
        ));
    }

    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $settings = DtrSetting::getSettings();
        $settings = $this->backupOriginalSchedule($settings);
        $settings = $this->applyFourDaySettings($settings);

        $month = (int) $request->input('month', date('m'));
        $year = (int) $request->input('year', date('Y'));

        $employee = DtrUser::where('emp_code', $user->emp_code)->first();

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        $dtrData = [];
        $presentDays = 0;
        $totalMinutes = 0;

        if ($employee) {
            $empCode = $employee->emp_code;
            $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');

            $dtrData = $this->computeDtr($empCode, $year, $month, $settings, $employee->default_work_week ?? null);
            $dtrData = $this->applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW);

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
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        $dtrData[$dayNum]['total_hours'] = '';
                        $dtrData[$dayNum]['remarks'] = 'Absent';
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'halfday_am':
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        if ($edit->field === 'am_out' && $edit->new_value) {
                            $dtrData[$dayNum]['am_out'] = $edit->new_value;
                        }
                        $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'halfday_pm':
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
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
                        $dtrData[$dayNum]['has_punch'] = true;
                        $wfhType = $edit->new_value ?: 'whole_day';
                        if ($wfhType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'WFH';
                            $dtrData[$dayNum]['am_out'] = 'WFH';
                            $dtrData[$dayNum]['remarks'] = 'WFH (AM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                        } elseif ($wfhType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'WFH';
                            $dtrData[$dayNum]['pm_out'] = 'WFH';
                            $dtrData[$dayNum]['remarks'] = 'WFH (PM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                        } else {
                            $dtrData[$dayNum]['am_in'] = 'WFH';
                            $dtrData[$dayNum]['am_out'] = 'WFH';
                            $dtrData[$dayNum]['pm_in'] = 'WFH';
                            $dtrData[$dayNum]['pm_out'] = 'WFH';
                            $dtrData[$dayNum]['is_wfh'] = true;
                            $dtrData[$dayNum]['remarks'] = 'WFH';
                            $dtrData[$dayNum]['total_hours'] = '08:00';
                        }
                        break;
                    case 'special_order':
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['so_number'] = $edit->new_value;
                        $dtrData[$dayNum]['remarks'] = 'Special Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                        $dtrData[$dayNum]['total_hours'] = '08:00';
                        break;
                    case 'travel_order':
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['to_number'] = $edit->new_value;
                        $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                        $dtrData[$dayNum]['total_hours'] = '08:00';
                        break;
                    case 'work_suspension':
                        $wsType = $edit->new_value ?: 'whole_day';
                        if ($wsType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'ABSENT';
                            $dtrData[$dayNum]['am_out'] = 'ABSENT';
                            $dtrData[$dayNum]['remarks'] = 'Work Suspension (AM)';
                        } elseif ($wsType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                            $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                            $dtrData[$dayNum]['remarks'] = 'Work Suspension (PM)';
                        } else {
                            $dtrData[$dayNum]['am_in'] = 'ABSENT';
                            $dtrData[$dayNum]['am_out'] = 'ABSENT';
                            $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                            $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                            $dtrData[$dayNum]['total_hours'] = '';
                            $dtrData[$dayNum]['remarks'] = 'Work Suspension';
                        }
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'locator_slip':
                        $lsType = $edit->field ?: 'official';
                        $whereabouts = $edit->new_value;
                        $typeLabel = $lsType === 'personal' ? 'Personal' : 'Official';
                        $timeLeft = $edit->ls_time_left ? date('h:i A', strtotime($edit->ls_time_left)) : '';
                        $timeReturned = $edit->ls_no_return ? 'No Return' : ($edit->ls_time_returned ? date('h:i A', strtotime($edit->ls_time_returned)) : '');
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['am_in'] = '';
                        $dtrData[$dayNum]['am_out'] = '';
                        $dtrData[$dayNum]['pm_in'] = '';
                        $dtrData[$dayNum]['pm_out'] = '';
                        $details = array_filter([$whereabouts, $timeLeft ? "Left: $timeLeft" : null, $timeReturned ? "Ret: $timeReturned" : null]);
                        $dtrData[$dayNum]['remarks'] = 'LS: ' . implode(' | ', $details) . " ($typeLabel)";
                        $dtrData[$dayNum]['total_hours'] = $edit->ls_no_return ? '04:00' : '08:00';
                        break;
                }
            }

            foreach ($dtrData as $dayNum => &$day) {
                if (isset($day['work_week_type'])) {
                    $schedule = $this->getScheduleForWorkWeek($day['work_week_type'], $settings);
                    $day['remarks'] = $this->recalcRemarks($day, $settings, $schedule);
                }
            }
            unset($day);

            foreach ($dtrData as $dayNum => $day) {
                $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
                $dayMaxDow = $empDefaultWW === '4-day' ? 4 : 5;
                if (!empty($day['has_punch']) && $dow <= $dayMaxDow) {
                    $presentDays++;
                    if (!empty($day['total_hours'])) {
                        $parts = explode(':', $day['total_hours']);
                        $totalMinutes += (int) $parts[0] * 60 + (int) $parts[1];
                    }
                }
            }
        }

        if (!isset($empDefaultWW)) {
            $empDefaultWW = ($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day';
        }

        $holidays = \App\Models\GlobalHoliday::whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->orderBy('target_date')
            ->get()
            ->keyBy(function ($item) {
                return $item->target_date->format('Y-m-d');
            });

        $firstDayOfWeek = date('N', strtotime("$year-$month-01"));
        $weeks = [];
        $day = 1;
        $totalCells = ceil(($firstDayOfWeek + $daysInMonth - 1) / 7) * 7;
        for ($i = 0; $i < $totalCells; $i++) {
            $weekIndex = intdiv($i, 7);
            if (!isset($weeks[$weekIndex])) $weeks[$weekIndex] = [];
            if ($i < $firstDayOfWeek - 1 || $day > $daysInMonth) {
                $weeks[$weekIndex][] = null;
            } else {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $dayData = $dtrData[$day] ?? [];
                $weeks[$weekIndex][] = [
                    'day' => $day,
                    'date' => $dateStr,
                    'holiday' => $holidays->get($dateStr),
                    'dtr' => $dayData,
                ];
                $day++;
            }
        }

        $totalHoursFormatted = sprintf('%02d:%02d', floor($totalMinutes / 60), $totalMinutes % 60);

        $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
        $isSupervisor = $user->is_super;
        if (!$isSupervisor && $dtrUser) {
            $isSupervisor = \App\Models\Section::where('supervisor_id', $dtrUser->id)->exists()
                || \App\Models\Office::where('supervisor_id', $dtrUser->id)->exists()
                || \App\Models\Office::where('oic_id', $dtrUser->id)->exists();
        }

        return view('dtr.dashboard', compact(
            'employee', 'settings', 'dtrData', 'month', 'year',
            'daysInMonth', 'monthName', 'presentDays', 'totalHoursFormatted',
            'weeks', 'empDefaultWW', 'isSupervisor'
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
        $settings = $this->backupOriginalSchedule($settings);
        $settings = $this->applyFourDaySettings($settings);
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        // Batch load all approved edit requests for ALL employees this month to avoid N+1
        $empIds = $employees->pluck('id');
        $allApprovedEdits = DtrEditRequest::with('employee')
            ->whereIn('employee_id', $empIds)
            ->whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->approved()
            ->get()
            ->groupBy(function ($edit) {
                return $edit->employee->emp_code;
            });

        $allDayOverrides = DtrDayOverride::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->get()
            ->groupBy('employee_id');

        $allDtrs = [];
        foreach ($employees as $employee) {
            $dtrData = $this->computeDtr($employee->emp_code, $year, $month, $settings, $employee->default_work_week ?? null);

            $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');

            $dtrData = $this->applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW);

            $approvedEdits = $allApprovedEdits->get($employee->emp_code, collect());

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
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        $dtrData[$dayNum]['total_hours'] = '';
                        $dtrData[$dayNum]['remarks'] = 'Absent';
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'halfday_am':
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        if ($edit->field === 'am_out' && $edit->new_value) {
                            $dtrData[$dayNum]['am_out'] = $edit->new_value;
                        }
                        $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'halfday_pm':
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
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
                        $dtrData[$dayNum]['has_punch'] = true;
                        $wfhType = $edit->new_value ?: 'whole_day';
                        if ($wfhType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'WFH';
                            $dtrData[$dayNum]['am_out'] = 'WFH';
                            $dtrData[$dayNum]['remarks'] = 'WFH (AM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        } elseif ($wfhType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'WFH';
                            $dtrData[$dayNum]['pm_out'] = 'WFH';
                            $dtrData[$dayNum]['remarks'] = 'WFH (PM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        } else {
                            $dtrData[$dayNum]['am_in'] = 'WFH';
                            $dtrData[$dayNum]['am_out'] = 'WFH';
                            $dtrData[$dayNum]['pm_in'] = 'WFH';
                            $dtrData[$dayNum]['pm_out'] = 'WFH';
                            $dtrData[$dayNum]['is_wfh'] = true;
                            $dtrData[$dayNum]['remarks'] = 'WFH';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                        }
                        break;
                    case 'special_order':
                        $dtrData[$dayNum]['remarks'] = 'Special Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['so_number'] = $edit->new_value;
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                        break;
                    case 'travel_order':
                        $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($edit->new_value ? ': ' . $edit->new_value : '');
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['to_number'] = $edit->new_value;
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                        break;
                    case 'work_suspension':
                        $wsType = $edit->new_value ?: 'whole_day';
                        if ($wsType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'ABSENT';
                            $dtrData[$dayNum]['am_out'] = 'ABSENT';
                            $dtrData[$dayNum]['remarks'] = 'Work Suspension (AM)';
                        } elseif ($wsType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                            $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                            $dtrData[$dayNum]['remarks'] = 'Work Suspension (PM)';
                        } else {
                            $dtrData[$dayNum]['am_in'] = 'ABSENT';
                            $dtrData[$dayNum]['am_out'] = 'ABSENT';
                            $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                            $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                            $dtrData[$dayNum]['total_hours'] = '';
                            $dtrData[$dayNum]['remarks'] = 'Work Suspension';
                        }
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'locator_slip':
                        $lsType = $edit->field ?: 'official';
                        $whereabouts = $edit->new_value;
                        $typeLabel = $lsType === 'personal' ? 'Personal' : 'Official';
                        $timeLeft = $edit->ls_time_left ? date('h:i A', strtotime($edit->ls_time_left)) : '';
                        $timeReturned = $edit->ls_no_return ? 'No Return' : ($edit->ls_time_returned ? date('h:i A', strtotime($edit->ls_time_returned)) : '');
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['am_in'] = '';
                        $dtrData[$dayNum]['am_out'] = '';
                        $dtrData[$dayNum]['pm_in'] = '';
                        $dtrData[$dayNum]['pm_out'] = '';
                        $details = array_filter([$whereabouts, $timeLeft ? "Left: $timeLeft" : null, $timeReturned ? "Ret: $timeReturned" : null]);
                        $dtrData[$dayNum]['remarks'] = 'LS: ' . implode(' | ', $details) . " ($typeLabel)";
                        $dtrData[$dayNum]['total_hours'] = $edit->ls_no_return ? '04:00' : ($empDefaultWW === '4-day' ? '10:00' : '08:00');
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

            foreach ($approvedEdits as $edit) {
                if (!in_array($edit->type, ['halfday_am', 'halfday_pm'])) continue;
                $dayNum = (int) $edit->target_date->format('j');
                if (strpos($dtrData[$dayNum]['remarks'] ?? '', 'Halfday') !== false) {
                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                }
            }

            $empOverrides = $allDayOverrides->get($employee->id, collect());
            foreach ($empOverrides as $override) {
                $dayNum = (int) $override->target_date->format('j');
                if (!isset($dtrData[$dayNum])) {
                    $dtrData[$dayNum] = ['am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '', 'total_hours' => '', 'remarks' => '', 'has_punch' => false];
                }
                $dtrData[$dayNum]['work_week_type'] = $override->work_week_type;
            }

            foreach ($dtrData as $dayNum => &$day) {
                if (!isset($day['work_week_type'])) continue;
                $schedule = $this->getScheduleForWorkWeek($day['work_week_type'], $settings);

                $wfhLabel = $this->resolveWfhLabel($day);

                $day['remarks'] = $this->recalcRemarks($day, $settings, $schedule);

                if (!empty($day['is_wfh']) || !empty($day['so_number']) || !empty($day['to_number'])) {
                    $day['total_hours'] = $day['work_week_type'] === '4-day' ? '10:00' : '08:00';
                }
                if (strpos($day['remarks'] ?? '', 'Halfday') !== false) {
                    $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                }

                if ($wfhLabel) {
                    $day['remarks'] = !empty($day['remarks']) ? $wfhLabel . ' | ' . $day['remarks'] : $wfhLabel;
                }

                if (strpos($wfhLabel, '(AM)') !== false || strpos($wfhLabel, '(PM)') !== false) {
                    $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                }
            }
            unset($day);

            $allDtrs[] = [
                'employee' => $employee,
                'dtrData' => $dtrData,
            ];
        }

        return view('dtr.print-all', compact('allDtrs', 'month', 'year', 'monthName', 'daysInMonth', 'settings'));
    }

    private function applyFourDaySettings($settings)
    {
        if (($settings['four_day_work_week'] ?? '0') === '1') {
            $settings['am_start'] = $settings['fdww_am_start'] ?? '07:00';
            $settings['am_end'] = $settings['fdww_am_end'] ?? '12:00';
            $settings['pm_start'] = $settings['fdww_pm_start'] ?? '13:00';
            $settings['pm_end'] = $settings['fdww_pm_end'] ?? '19:00';
            $settings['am_start_flexi'] = $settings['fdww_am_start_flexi'] ?? '60';
            $settings['pm_end_flexi'] = $settings['fdww_pm_end_flexi'] ?? '60';
            $settings['max_dow'] = 4;
        } else {
            $settings['max_dow'] = 5;
        }
        return $settings;
    }

    private function backupOriginalSchedule($settings)
    {
        $settings['original_am_start'] = $settings['am_start'] ?? '07:00';
        $settings['original_am_end'] = $settings['am_end'] ?? '12:00';
        $settings['original_pm_start'] = $settings['pm_start'] ?? '13:00';
        $settings['original_pm_end'] = $settings['pm_end'] ?? '17:00';
        return $settings;
    }

    private function applyWorkWeekSettings($settings, $workWeekType)
    {
        if ($workWeekType === '4-day') {
            $settings['am_start'] = $settings['fdww_am_start'] ?? '07:00';
            $settings['am_end'] = $settings['fdww_am_end'] ?? '12:00';
            $settings['pm_start'] = $settings['fdww_pm_start'] ?? '13:00';
            $settings['pm_end'] = $settings['fdww_pm_end'] ?? '19:00';
            $settings['am_start_flexi'] = $settings['fdww_am_start_flexi'] ?? '60';
            $settings['pm_end_flexi'] = $settings['fdww_pm_end_flexi'] ?? '60';
            $settings['max_dow'] = 4;
        } else {
            $settings['am_start'] = '07:00';
            $settings['am_end'] = $settings['original_am_end'] ?? '12:00';
            $settings['pm_start'] = $settings['original_pm_start'] ?? '13:00';
            $settings['pm_end'] = $settings['original_pm_end'] ?? '17:00';
            $settings['am_start_flexi'] = '120';
            $settings['pm_end_flexi'] = $settings['pm_end_flexi'] ?? '60';
            $settings['max_dow'] = 5;
        }
        return $settings;
    }

    private function getScheduleForWorkWeek($workWeekType, $settings)
    {
        $local = $this->applyWorkWeekSettings($settings, $workWeekType);
        return [
            'am_start' => $local['am_start'],
            'am_end' => $local['am_end'],
            'pm_start' => $local['pm_start'],
            'pm_end' => $local['pm_end'],
            'am_start_flexi' => ((int)($local['am_start_flexi'] ?? 60)) * 60,
            'pm_end_flexi' => ((int)($local['pm_end_flexi'] ?? 60)) * 60,
            'max_dow' => $local['max_dow'],
        ];
    }

    private function checkIsSupervisor($user)
    {
        if ($user->is_super) return true;
        $dtrU = DtrUser::where('emp_code', $user->emp_code)->first();
        if (!$dtrU) return false;
        return Section::where('supervisor_id', $dtrU->id)->exists()
            || \App\Models\Office::where('supervisor_id', $dtrU->id)->exists();
    }

    private function recalcHours($day, $settings)
    {
        $amIn = $day['am_in'] ?? '';
        $amOut = $day['am_out'] ?? '';
        $pmIn = $day['pm_in'] ?? '';
        $pmOut = $day['pm_out'] ?? '';

        $hasAmIn = $amIn !== '' && preg_match('/^\d/', $amIn);
        $hasAmOut = $amOut !== '' && preg_match('/^\d/', $amOut);
        $hasPmIn = $pmIn !== '' && preg_match('/^\d/', $pmIn);
        $hasPmOut = $pmOut !== '' && preg_match('/^\d/', $pmOut);

        $hasAm = $hasAmIn || $hasAmOut;
        $hasPm = $hasPmIn || $hasPmOut;

        if (!$hasAm && !$hasPm) return '';

        if ($hasAmIn && $hasPmOut) {
            $amInTS = strtotime($amIn);
            $pmOutTS = strtotime($pmOut);
            $totalMinutesWorked = ($pmOutTS - $amInTS) / 60;
            $lunchBreak = 60;
            if ($hasAmOut && $hasPmIn) {
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

        if ($hasPmIn && $hasPmOut) {
            $pmInTS = strtotime($pmIn);
            $pmOutTS = strtotime($pmOut);
            $totalMin = ($pmOutTS - $pmInTS) / 60;
            if ($totalMin < 0) $totalMin = 0;
            $hours = floor($totalMin / 60);
            $mins = round($totalMin % 60);
            return sprintf('%02d:%02d', $hours, $mins);
        }

        if ($hasAmIn && $hasAmOut) {
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

    private function recalcRemarks($day, $settings, $scheduleOverride = null)
    {
        if ($scheduleOverride) {
            $settingsAmStart = $scheduleOverride['am_start'];
            $settingsPmStart = $scheduleOverride['pm_start'];
            $settingsPmEnd = $scheduleOverride['pm_end'];
            $amStartFlexi = $scheduleOverride['am_start_flexi'];
            $pmEndFlexi = $scheduleOverride['pm_end_flexi'];
        } else {
            $settings = $this->applyFourDaySettings($settings);
            $settingsAmStart = $settings['am_start'] ?? '07:00';
            $settingsPmStart = $settings['pm_start'] ?? '13:00';
            $settingsPmEnd = $settings['pm_end'] ?? '17:00';
            $amStartFlexi = ((int)($settings['am_start_flexi'] ?? 120)) * 60;
            $pmEndFlexi = ((int)($settings['pm_end_flexi'] ?? 60)) * 60;
        }

        $amIn = $day['am_in'] ?? '';
        $amOut = $day['am_out'] ?? '';
        $pmIn = $day['pm_in'] ?? '';
        $pmOut = $day['pm_out'] ?? '';

        $remarks = [];

        $lateAM = 0;
        if ($amIn !== '' && preg_match('/^\d/', $amIn)) {
            $amStartTS = strtotime($settingsAmStart);
            $amLateThreshold = $amStartTS + $amStartFlexi;
            $amInTS = strtotime($amIn);
            if ($amInTS > $amLateThreshold) {
                $lateAM = ($amInTS - $amLateThreshold) / 60;
            }
        }
        $latePM = 0;
        if ($pmIn !== '' && preg_match('/^\d/', $pmIn)) {
            $pmStartTS = strtotime($settingsPmStart);
            $pmInTS = strtotime($pmIn);
            if ($pmInTS > $pmStartTS) {
                $latePM = ($pmInTS - $pmStartTS) / 60;
            }
        }
        if ($lateAM > 0 || $latePM > 0) {
            $parts = [];
            if ($lateAM > 0) $parts[] = 'AM ' . gmdate('H:i', $lateAM * 60);
            if ($latePM > 0) $parts[] = 'PM ' . gmdate('H:i', $latePM * 60);
            $remarks[] = 'Late: ' . implode(' + ', $parts);
        }

        $undertimeMinutes = 0;
        if ($pmIn && preg_match('/^\d/', $pmIn) && $pmOut) {
            $pmEndTS = strtotime($settingsPmEnd);
            $pmUndertimeThreshold = $pmEndTS - $pmEndFlexi;
            $pmOutTS = strtotime($pmOut);
            if ($pmOutTS < $pmUndertimeThreshold) {
                $undertimeMinutes = ($pmEndTS - $pmOutTS) / 60;
            }
        }
        if ($undertimeMinutes > 0) {
            $remarks[] = 'UT: ' . gmdate('H:i', $undertimeMinutes * 60);
        }

        return implode(' | ', $remarks);
    }

    private function computeDtr($empCode, $year, $month, $settings, $defaultWorkWeek = null)
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $firstDay = "$year-$month-01";
        $lastDay = "$year-$month-$daysInMonth";

        $punches = IclockTransaction::forEmployee($empCode)
            ->forPeriod($firstDay, $lastDay)
            ->ordered()
            ->get();

        $settings = $this->applyFourDaySettings($settings);
        if ($defaultWorkWeek) {
            $settings = $this->applyWorkWeekSettings($settings, $defaultWorkWeek);
        }
        $settingsAmStart = $settings['am_start'] ?? '07:00';
        $settingsAmEnd = $settings['am_end'] ?? '12:00';
        $settingsPmStart = $settings['pm_start'] ?? '13:00';
        $settingsPmEnd = $settings['pm_end'] ?? '17:00';
        $amStartFlexi = ((int)($settings['am_start_flexi'] ?? 120)) * 60;
        $pmEndFlexi = ((int)($settings['pm_end_flexi'] ?? 60)) * 60;

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

                if ($t <= $amEnd) {
                    if (abs($t - $amStart) <= abs($t - $amEnd)) {
                        if ($amIn === null || $t < $amIn) $amIn = $t;
                    } else {
                        if ($amOut === null || $t > $amOut) $amOut = $t;
                    }
                } else {
                    if (abs($t - $pmStart) <= abs($t - $pmEnd)) {
                        if ($pmIn === null || $t < $pmIn) $pmIn = $t;
                    } else {
                        if ($pmOut === null || $t > $pmOut) $pmOut = $t;
                    }
                }
            }

            $remarks = [];

            $lateAM = 0;
            $amLateThreshold = $amStart + $amStartFlexi;
            if ($amIn && $amIn > $amLateThreshold) {
                $lateAM = ($amIn - $amLateThreshold) / 60;
            }
            $latePM = 0;
            if ($pmIn && $pmIn > $pmStart) {
                $latePM = ($pmIn - $pmStart) / 60;
            }
            if ($lateAM > 0 || $latePM > 0) {
                $parts = [];
                if ($lateAM > 0) $parts[] = 'AM ' . gmdate('H:i', $lateAM * 60);
                if ($latePM > 0) $parts[] = 'PM ' . gmdate('H:i', $latePM * 60);
                $remarks[] = 'Late: ' . implode(' + ', $parts);
            }

            $undertimeMinutes = 0;
            $pmUndertimeThreshold = $pmEnd - $pmEndFlexi;
            if ($pmOut && $pmOut < $pmUndertimeThreshold) {
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

    public function toggleWorkWeek(Request $request)
    {
        $setting = DtrSetting::where('setting_key', 'four_day_work_week')->first();
        if ($setting) {
            $val = $request->input('value');
            if (!in_array($val, ['0', '1'])) {
                return response()->json(['success' => false], 400);
            }
            $setting->update(['setting_value' => $val]);
            return response()->json(['success' => true, 'value' => $val]);
        }
        return response()->json(['success' => false], 400);
    }

    public function toggleDayWorkWeek(Request $request)
    {
        $user = auth()->user();
        $employee = DtrUser::where('emp_code', $user->emp_code)->firstOrFail();

        $data = $request->validate([
            'target_date' => 'required|date',
            'work_week_type' => 'required|in:5-day,4-day',
        ]);

        DtrDayOverride::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'target_date' => $data['target_date'],
            ],
            [
                'work_week_type' => $data['work_week_type'],
            ]
        );

        return response()->json(['success' => true]);
    }

    private function applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW)
    {
        $globalHolidays = Cache::remember('global_holidays.' . $year . '.' . $month, 1440, function () use ($year, $month) {
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            return GlobalHoliday::whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
                ->orderBy('target_date')
                ->get();
        });

        foreach ($globalHolidays as $holiday) {
            $dayNum = (int) $holiday->target_date->format('j');

            if (!isset($dtrData[$dayNum])) {
                $dtrData[$dayNum] = [
                    'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                    'total_hours' => '', 'remarks' => '', 'has_punch' => false,
                ];
            }

            $desc = $holiday->description ? ' (' . $holiday->description . ')' : '';

            if ($holiday->type === 'holiday') {
                if ($holiday->value === 'am') {
                    $dtrData[$dayNum]['am_in'] = 'HOLIDAY';
                    $dtrData[$dayNum]['am_out'] = 'HOLIDAY';
                    $dtrData[$dayNum]['remarks'] = 'Holiday (AM)' . $desc;
                } elseif ($holiday->value === 'pm') {
                    $dtrData[$dayNum]['pm_in'] = 'HOLIDAY';
                    $dtrData[$dayNum]['pm_out'] = 'HOLIDAY';
                    $dtrData[$dayNum]['remarks'] = 'Holiday (PM)' . $desc;
                } else {
                    $dtrData[$dayNum]['am_in'] = 'HOLIDAY';
                    $dtrData[$dayNum]['am_out'] = 'HOLIDAY';
                    $dtrData[$dayNum]['pm_in'] = 'HOLIDAY';
                    $dtrData[$dayNum]['pm_out'] = 'HOLIDAY';
                    $dtrData[$dayNum]['is_holiday'] = true;
                    $dtrData[$dayNum]['remarks'] = 'Holiday' . $desc;
                }
                $dtrData[$dayNum]['has_punch'] = true;
            } elseif ($holiday->type === 'work_suspension') {
                if ($holiday->value === 'am') {
                    $dtrData[$dayNum]['am_in'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['am_out'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['remarks'] = 'Work Suspension (AM)' . $desc;
                } elseif ($holiday->value === 'pm') {
                    $dtrData[$dayNum]['pm_in'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['pm_out'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['remarks'] = 'Work Suspension (PM)' . $desc;
                } else {
                    $dtrData[$dayNum]['am_in'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['am_out'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['pm_in'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['pm_out'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['total_hours'] = '';
                    $dtrData[$dayNum]['remarks'] = 'Work Suspension' . $desc;
                }
                $dtrData[$dayNum]['has_punch'] = true;
            }
        }

        return $dtrData;
    }

    private function resolveWfhLabel($day)
    {
        if (!empty($day['is_wfh'])) return 'WFH';
        if (!empty($day['so_number'])) return 'Special Order' . (!empty($day['so_number']) ? ': ' . $day['so_number'] : '');
        if (!empty($day['to_number'])) return 'Travel Order' . (!empty($day['to_number']) ? ': ' . $day['to_number'] : '');

        $am = $day['am_in'] ?? '';
        $pm = $day['pm_in'] ?? '';

        if (strpos($am, 'SO:') === 0 && strpos($pm, 'SO:') === 0) return 'Special Order: ' . substr($am, 4);
        if (strpos($am, 'SO:') === 0) return 'Special Order: ' . substr($am, 4) . ' (AM)';
        if (strpos($pm, 'SO:') === 0) return 'Special Order: ' . substr($pm, 4) . ' (PM)';

        if (strpos($am, 'TO:') === 0 && strpos($pm, 'TO:') === 0) return 'Travel Order: ' . substr($am, 4);
        if (strpos($am, 'TO:') === 0) return 'Travel Order: ' . substr($am, 4) . ' (AM)';
        if (strpos($pm, 'TO:') === 0) return 'Travel Order: ' . substr($pm, 4) . ' (PM)';

        if ($am === 'WFH' && $pm === 'WFH') return 'WFH';
        if ($am === 'WFH') return 'WFH (AM)';
        if ($pm === 'WFH') return 'WFH (PM)';

        if (strpos($am, 'LS:') === 0 && strpos($pm, 'LS:') === 0) return 'Locator Slip: ' . substr($am, 4);
        if (strpos($am, 'LS:') === 0) return 'Locator Slip: ' . substr($am, 4) . ' (AM)';
        if (strpos($pm, 'LS:') === 0) return 'Locator Slip: ' . substr($pm, 4) . ' (PM)';

        return '';
    }

}
