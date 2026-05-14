<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrDayOverride extends Model
{
    protected $fillable = [
        'employee_id', 'target_date', 'work_week_type',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(DtrUser::class, 'employee_id');
    }
}
