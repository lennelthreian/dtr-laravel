<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\LogsUserActivity;

class GlobalHoliday extends Model
{
    use LogsUserActivity;
    protected $fillable = [
        'target_date', 'type', 'value', 'start_time', 'end_time', 'description',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];
}
