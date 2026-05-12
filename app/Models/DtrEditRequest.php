<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrEditRequest extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'target_date', 'field', 'old_value', 'new_value',
        'reason', 'status', 'reviewer_id', 'reviewed_at', 'rejection_reason',
    ];

    protected $casts = [
        'target_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(DtrUser::class, 'employee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(DtrUser::class, 'reviewer_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForEmployee($query, $empCode)
    {
        return $query->whereHas('employee', function ($q) use ($empCode) {
            $q->where('emp_code', $empCode);
        });
    }

    public function scopeForPeriod($query, $year, $month)
    {
        return $query->whereYear('target_date', $year)->whereMonth('target_date', $month);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
