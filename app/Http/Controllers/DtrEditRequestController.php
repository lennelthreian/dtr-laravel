<?php

namespace App\Http\Controllers;

use App\Models\DtrEditRequest;
use App\Models\DtrSetting;
use App\Models\DtrUser;
use App\Models\Office;
use App\Models\Section;
use App\Models\User;
use App\Notifications\EditRequestApproved;
use App\Notifications\EditRequestRejected;
use App\Notifications\EditRequestSubmitted;
use Illuminate\Http\Request;

class DtrEditRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:time_correction,absent,halfday_am,halfday_pm,holiday,wfh,special_order,travel_order',
            'target_date' => 'required|date',
            'field' => 'nullable|in:am_in,am_out,pm_in,pm_out',
            'old_value' => 'nullable|string|max:10',
            'new_value' => 'nullable|string|max:10',
            'reason' => 'required|string|max:500',
        ]);

        $user = auth()->user();
        $employee = DtrUser::where('emp_code', $user->emp_code)->firstOrFail();

        $payload = [
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'target_date' => $data['target_date'],
            'reason' => $data['reason'],
        ];

        if ($data['type'] === 'time_correction') {
            $request->validate([
                'field' => 'required|in:am_in,am_out,pm_in,pm_out',
                'new_value' => 'required|string|max:10',
            ]);
            $payload['field'] = $data['field'];
            $payload['old_value'] = $data['old_value'];
            $payload['new_value'] = $data['new_value'];
        } elseif (in_array($data['type'], ['halfday_am', 'halfday_pm'])) {
            $payload['field'] = $data['field'] ?: ($data['type'] === 'halfday_am' ? 'am_out' : 'pm_in');
            $payload['new_value'] = $data['new_value'] ?: '';
        } elseif ($data['type'] === 'special_order') {
            $soData = $request->validate([
                'so_number' => 'required|string|max:100',
            ]);
            $payload['field'] = '';
            $payload['new_value'] = $soData['so_number'];
        } elseif ($data['type'] === 'travel_order') {
            $toData = $request->validate([
                'to_number' => 'required|string|max:100',
            ]);
            $payload['field'] = '';
            $payload['new_value'] = $toData['to_number'];
        } else {
            $payload['field'] = '';
            $payload['new_value'] = '';
        }

        $editRequest = DtrEditRequest::create($payload);

        $this->notifySupervisors($editRequest, $employee);

        return back()->with('success', 'Edit DTR request is successfully sent to the supervisor.');
    }

    public function approve(DtrEditRequest $editRequest)
    {
        $this->ensureSupervisor($editRequest);

        $editRequest->update([
            'status' => 'approved',
            'reviewer_id' => $this->getSupervisorDtrUserId(),
            'reviewed_at' => now(),
        ]);

        $this->notifyEmployee($editRequest, 'approved');

        return back()->with('success', 'Edit request approved.');
    }

    public function reject(Request $request, DtrEditRequest $editRequest)
    {
        $this->ensureSupervisor($editRequest);

        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $editRequest->update([
            'status' => 'rejected',
            'reviewer_id' => $this->getSupervisorDtrUserId(),
            'reviewed_at' => now(),
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        $this->notifyEmployee($editRequest, 'rejected');

        return back()->with('success', 'Edit request rejected.');
    }

    public function markAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    }

    public function markSingleAsRead($id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
        return response()->json(['success' => true]);
    }

    private function notifySupervisors(DtrEditRequest $editRequest, DtrUser $employee)
    {
        $section = Section::find($employee->section_id);
        if ($section && $section->supervisor_id && $section->supervisor_id != $employee->id) {
            $supervisorDtr = DtrUser::find($section->supervisor_id);
            if ($supervisorDtr) {
                $supervisorUser = User::where('emp_code', $supervisorDtr->emp_code)->first();
                if ($supervisorUser) {
                    $supervisorUser->notify(new EditRequestSubmitted($editRequest));
                }
            }
        }

        $office = Office::find($employee->office_id);
        if ($office && $office->supervisor_id && $office->supervisor_id != $employee->id) {
            $officeSupervisorDtr = DtrUser::find($office->supervisor_id);
            if ($officeSupervisorDtr) {
                $officeSupervisorUser = User::where('emp_code', $officeSupervisorDtr->emp_code)->first();
                if ($officeSupervisorUser) {
                    $officeSupervisorUser->notify(new EditRequestSubmitted($editRequest));
                }
            }
        }

        $isOfficeSupervisor = Office::where('supervisor_id', $employee->id)->exists();

        if ($isOfficeSupervisor && $office && $office->senior_manager_id && $office->senior_manager_id != $employee->id) {
            $seniorManagerDtr = DtrUser::find($office->senior_manager_id);
            if ($seniorManagerDtr) {
                $seniorManagerUser = User::where('emp_code', $seniorManagerDtr->emp_code)->first();
                if ($seniorManagerUser) {
                    $seniorManagerUser->notify(new EditRequestSubmitted($editRequest));
                }
            }
        }

        if ($office && $office->oic_id && $office->oic_id != $employee->id) {
            $oicDtr = DtrUser::find($office->oic_id);
            if ($oicDtr) {
                $oicUser = User::where('emp_code', $oicDtr->emp_code)->first();
                if ($oicUser) {
                    $oicUser->notify(new EditRequestSubmitted($editRequest));
                }
            }
        }

        if ($isOfficeSupervisor) {
            $ahUserId = DtrSetting::where('setting_key', 'agency_head_user_id')->value('setting_value');
            if ($ahUserId) {
                $ahUser = User::find($ahUserId);
                if ($ahUser && $ahUser->id != auth()->id()) {
                    $ahUser->notify(new EditRequestSubmitted($editRequest));
                }
            }
        }
    }

    private function notifyEmployee(DtrEditRequest $editRequest, $status)
    {
        $employeeUser = User::where('emp_code', $editRequest->employee->emp_code)->first();
        if (!$employeeUser) return;

        if ($status === 'approved') {
            $employeeUser->notify(new EditRequestApproved($editRequest));
        } else {
            $employeeUser->notify(new EditRequestRejected($editRequest));
        }
    }

    private function ensureSupervisor(DtrEditRequest $editRequest)
    {
        $user = auth()->user();
        if ($user->is_super) return;

        $supervisorId = $this->getSupervisorDtrUserId();
        if (!$supervisorId) {
            abort(403, 'You are not assigned as a supervisor.');
        }

        $employee = $editRequest->employee;

        $sectionIds = Section::where('supervisor_id', $supervisorId)->pluck('id');
        if (in_array($employee->section_id, $sectionIds->toArray())) {
            return;
        }

        $officeIds = Office::where('supervisor_id', $supervisorId)->pluck('id');
        if (in_array($employee->office_id, $officeIds->toArray())) {
            return;
        }

        $seniorManagerOfficeIds = Office::where('senior_manager_id', $supervisorId)->pluck('id');
        if (in_array($employee->office_id, $seniorManagerOfficeIds->toArray())) {
            return;
        }

        $oicOfficeIds = Office::where('oic_id', $supervisorId)->pluck('id');
        if (in_array($employee->office_id, $oicOfficeIds->toArray())) {
            return;
        }

        abort(403, 'You are not the supervisor of this employee.');
    }

    private function getSupervisorDtrUserId()
    {
        $user = auth()->user();
        if ($user->is_super) return null;

        $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
        return $dtrUser ? $dtrUser->id : null;
    }
}
