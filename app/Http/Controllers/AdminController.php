<?php

namespace App\Http\Controllers;

use App\Models\DtrSetting;
use App\Models\DtrUser;
use App\Models\GlobalHoliday;
use App\Models\Office;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'super']);
    }

    public function dashboard()
    {
        $officeCount = Office::count();
        $sectionCount = Section::count();
        $employeeCount = DtrUser::count();
        $unassignedCount = DtrUser::whereNull('section_id')->count();
        return view('admin.dashboard', compact('officeCount', 'sectionCount', 'employeeCount', 'unassignedCount'));
    }

    public function offices()
    {
        $offices = Office::with(['sections', 'supervisor', 'seniorManager', 'oic'])->withCount('sections')->orderBy('name')->get();
        $employees = DtrUser::orderBy('last_name')->orderBy('first_name')->get();
        return view('admin.offices', compact('offices', 'employees'));
    }

    public function storeOffice(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:200|unique:offices']);
        Office::create($data);
        return redirect()->route('admin.offices')->with('success', 'Office created.');
    }

    public function deleteOffice(Office $office)
    {
        $office->delete();
        return redirect()->route('admin.offices')->with('success', 'Office deleted.');
    }

    public function assignOfficeSupervisor(Request $request, Office $office)
    {
        $data = $request->validate([
            'supervisor_id' => 'nullable|exists:dtr_users,id',
        ]);
        $office->update(['supervisor_id' => $data['supervisor_id']]);
        return redirect()->route('admin.offices')->with('success', 'Office supervisor assigned.');
    }

    public function assignSeniorManager(Request $request, Office $office)
    {
        $data = $request->validate([
            'senior_manager_id' => 'nullable|exists:dtr_users,id',
        ]);
        $office->update(['senior_manager_id' => $data['senior_manager_id']]);
        return redirect()->route('admin.offices')->with('success', 'Senior manager assigned.');
    }

    public function assignOic(Request $request, Office $office)
    {
        $data = $request->validate([
            'oic_id' => 'nullable|exists:dtr_users,id',
        ]);
        $office->update(['oic_id' => $data['oic_id']]);
        return redirect()->route('admin.offices')->with('success', 'OIC assigned.');
    }

    public function sections()
    {
        $offices = Office::with('sections')->orderBy('name')->get();
        $sections = Section::with(['office', 'supervisor'])->orderBy('name')->get();
        $employees = DtrUser::orderBy('last_name')->orderBy('first_name')->get();
        return view('admin.sections', compact('offices', 'sections', 'employees'));
    }

    public function storeSection(Request $request)
    {
        $data = $request->validate([
            'office_id' => 'required|exists:offices,id',
            'name' => 'required|string|max:200',
        ]);
        Section::create($data);
        return redirect()->route('admin.sections')->with('success', 'Section created.');
    }

    public function deleteSection(Section $section)
    {
        $section->delete();
        return redirect()->route('admin.sections')->with('success', 'Section deleted.');
    }

    public function assignSectionSupervisor(Request $request, Section $section)
    {
        $data = $request->validate([
            'supervisor_id' => 'nullable|exists:dtr_users,id',
        ]);
        $section->update(['supervisor_id' => $data['supervisor_id']]);
        return redirect()->route('admin.sections')->with('success', 'Section supervisor assigned.');
    }

    public function employees()
    {
        $employees = DtrUser::with(['officeModel', 'sectionModel'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $offices = Office::with('sections')->orderBy('name')->get();
        return view('admin.employees', compact('employees', 'offices'));
    }

    public function assignEmployee(Request $request, DtrUser $employee)
    {
        $data = $request->validate([
            'office_id' => 'nullable|exists:offices,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $office = $data['office_id'] ? Office::find($data['office_id']) : null;
        $section = $data['section_id'] ? Section::find($data['section_id']) : null;

        $employee->update([
            'office_id' => $data['office_id'],
            'section_id' => $data['section_id'],
            'office' => $office ? $office->name : '',
            'section' => $section ? $section->name : '',
        ]);

        return redirect()->route('admin.employees')->with('success', 'Employee assigned.');
    }

    public function resetPassword(DtrUser $employee)
    {
        $user = User::where('emp_code', $employee->emp_code)->first();

        if (!$user) {
            return redirect()->route('admin.employees')
                ->with('error', 'No user account found for this employee.');
        }

        $user->update(['password' => Hash::make('password')]);

        return redirect()->route('admin.employees')
            ->with('success', "Password for {$employee->full_name} reset to \"password\".");
    }

    public function settings()
    {
        $order = ['system_name', 'logo_path', 'agency_head_name', 'agency_head_position', 'agency_head_user_id', 'agency_name', 'four_day_work_week', 'am_start', 'am_start_flexi', 'am_end', 'pm_start', 'pm_end', 'pm_end_flexi', 'fdww_am_start', 'fdww_am_start_flexi', 'fdww_am_end', 'fdww_pm_start', 'fdww_pm_end', 'fdww_pm_end_flexi'];
        $settingModels = DtrSetting::whereNotIn('setting_key', ['grace_period_minutes', 'office_name'])
            ->orderByRaw('FIELD(setting_key, "' . implode('","', $order) . '")')
            ->get();
        $users = User::orderBy('name')->get(['id', 'name', 'emp_code']);
        return view('admin.settings', compact('settingModels', 'users'));
    }

    public function updateSettings(Request $request)
    {
        $keys = DtrSetting::pluck('setting_key')->toArray();
        foreach ($keys as $key) {
            if ($key === 'logo_path') {
                continue;
            }
            if ($request->has($key)) {
                DtrSetting::where('setting_key', $key)->update([
                    'setting_value' => $request->input($key),
                ]);
            }
        }

        if ($request->hasFile('logo')) {
            $old = DtrSetting::where('setting_key', 'logo_path')->value('setting_value');
            if ($old) {
                Storage::delete('public/' . $old);
            }
            $path = $request->file('logo')->store('public/logos');
            $relativePath = str_replace('public/', '', $path);
            DtrSetting::where('setting_key', 'logo_path')->update([
                'setting_value' => $relativePath,
            ]);
        }

        return redirect()->route('admin.settings')->with('success', 'Settings updated.');
    }

    public function holidays(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $holidays = GlobalHoliday::whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
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
                $weeks[$weekIndex][] = [
                    'day' => $day,
                    'date' => $dateStr,
                    'holiday' => $holidays->get($dateStr),
                ];
                $day++;
            }
        }

        return view('admin.holidays', compact('holidays', 'month', 'year', 'daysInMonth', 'weeks'));
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate([
            'target_date' => 'required|date|unique:global_holidays,target_date',
            'type' => 'required|in:holiday,work_suspension',
            'value' => 'required|in:whole_day,am,pm',
            'description' => 'nullable|string|max:200',
        ]);

        GlobalHoliday::create($data);
        $m = date('m', strtotime($data['target_date']));
        $y = date('Y', strtotime($data['target_date']));
        Cache::forget('global_holidays.' . $y . '.' . $m);

        return redirect()->route('admin.holidays', ['month' => $m, 'year' => $y])
            ->with('success', ucwords(str_replace('_', ' ', $data['type'])) . ' set for ' . date('M d, Y', strtotime($data['target_date'])));
    }

    public function deleteHoliday(GlobalHoliday $holiday)
    {
        $month = $holiday->target_date->format('m');
        $year = $holiday->target_date->format('Y');
        $holiday->delete();
        Cache::forget('global_holidays.' . $year . '.' . $month);

        return redirect()->route('admin.holidays', ['month' => $month, 'year' => $year])
            ->with('success', 'Removed.');
    }

    public function workArrangement()
    {
        $employees = DtrUser::orderBy('last_name')->orderBy('first_name')->get();
        $globalSetting = DtrSetting::where('setting_key', 'four_day_work_week')->first();
        return view('admin.work-arrangement', compact('employees', 'globalSetting'));
    }

    public function updateGlobalWorkWeek(Request $request)
    {
        $data = $request->validate(['value' => 'required|in:0,1']);
        $setting = DtrSetting::where('setting_key', 'four_day_work_week')->first();
        if ($setting) {
            $setting->setting_value = $data['value'];
            $setting->save();
        }
        return redirect()->route('admin.work-arrangement')->with('success', 'Global work week updated.');
    }

    public function updateEmployeeWorkWeek(Request $request, DtrUser $employee)
    {
        $data = $request->validate(['default_work_week' => 'required|in:5-day,4-day,default']);
        $value = $data['default_work_week'] === 'default' ? null : $data['default_work_week'];
        $employee->update(['default_work_week' => $value]);
        return redirect()->route('admin.work-arrangement')->with('success', "{$employee->full_name} updated.");
    }
}
