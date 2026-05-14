<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalHoliday extends Model
{
    protected $fillable = [
        'target_date', 'type', 'value', 'description',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];
}
