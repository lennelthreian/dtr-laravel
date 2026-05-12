<?php

namespace App\Http\Controllers;

use App\Models\DtrSetting;
use App\Models\DtrUser;
use App\Models\Office;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
        $order = ['agency_head_name', 'agency_head_position', 'agency_head_user_id', 'agency_name', 'am_start', 'am_end', 'pm_start', 'pm_end'];
        $settings = DtrSetting::whereNotIn('setting_key', ['grace_period_minutes', 'office_name'])
            ->orderByRaw('FIELD(setting_key, "' . implode('","', $order) . '")')
            ->get();
        $users = User::orderBy('name')->get(['id', 'name', 'emp_code']);
        return view('admin.settings', compact('settings', 'users'));
    }

    public function updateSettings(Request $request)
    {
        $keys = DtrSetting::pluck('setting_key')->toArray();
        foreach ($keys as $key) {
            if ($request->has($key)) {
                DtrSetting::where('setting_key', $key)->update([
                    'setting_value' => $request->input($key),
                ]);
            }
        }
        return redirect()->route('admin.settings')->with('success', 'Settings updated.');
    }
}
