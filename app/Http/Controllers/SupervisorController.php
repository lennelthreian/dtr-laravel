<?php

namespace App\Http\Controllers;

use App\Models\DtrEditRequest;
use App\Models\DtrUser;
use App\Models\Office;
use App\Models\Section;
use Illuminate\Http\Request;

class SupervisorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function pending()
    {
        $user = auth()->user();

        if ($user->is_super) {
            $requests = DtrEditRequest::with('employee')
                ->pending()
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(function ($r) {
                    return $r->employee->full_name . ' (' . $r->employee->emp_code . ')';
                });
        } else {
            $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
            if (!$dtrUser) {
                return view('supervisor.pending', ['grouped' => collect()]);
            }

            $sectionIds = Section::where('supervisor_id', $dtrUser->id)->pluck('id');
            $officeIds = Office::where('supervisor_id', $dtrUser->id)->pluck('id');

            $requests = DtrEditRequest::with('employee')
                ->where(function ($q) use ($sectionIds, $officeIds) {
                    if ($sectionIds->isNotEmpty()) {
                        $q->whereHas('employee', function ($q) use ($sectionIds) {
                            $q->whereIn('section_id', $sectionIds);
                        });
                    }
                    if ($officeIds->isNotEmpty()) {
                        $q->orWhereHas('employee', function ($q) use ($officeIds) {
                            $q->whereIn('office_id', $officeIds);
                        });
                    }
                })
                ->pending()
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(function ($r) {
                    return $r->employee->full_name . ' (' . $r->employee->emp_code . ')';
                });
        }

        return view('supervisor.pending', ['grouped' => $requests]);
    }

    public function getSupervisorSectionIds()
    {
        $user = auth()->user();
        if ($user->is_super) return null;

        $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
        if (!$dtrUser) return collect();

        return Section::where('supervisor_id', $dtrUser->id)->pluck('id');
    }
}
