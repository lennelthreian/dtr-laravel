<?php

namespace App\Notifications;

use App\Models\DtrEditRequest;
use Illuminate\Notifications\Notification;

class EditRequestSubmitted extends Notification
{
    public $editRequest;

    public function __construct(DtrEditRequest $editRequest)
    {
        $this->editRequest = $editRequest;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $employee = $this->editRequest->employee;

        $typeLabels = [
            'time_correction' => 'Time Correction',
            'absent' => 'Whole Day Absent',
            'halfday_am' => 'Halfday (AM)',
            'halfday_pm' => 'Halfday (PM)',
            'holiday' => 'Holiday',
            'wfh' => 'WFH',
            'special_order' => 'Special Order',
            'travel_order' => 'Travel Order',
        ];

        return [
            'type' => 'edit_request_submitted',
            'edit_request_id' => $this->editRequest->id,
            'employee_name' => $employee->full_name,
            'employee_id' => $employee->id,
            'request_type' => $typeLabels[$this->editRequest->type] ?? $this->editRequest->type,
            'target_date' => $this->editRequest->target_date->format('Y-m-d'),
            'message' => $employee->full_name . ' submitted a ' . ($typeLabels[$this->editRequest->type] ?? $this->editRequest->type) . ' request for ' . $this->editRequest->target_date->format('M d, Y'),
        ];
    }
}
