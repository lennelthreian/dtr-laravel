<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrMonthlyShare extends Model
{
    protected $table = 'dtr_monthly_shares';

    protected $fillable = [
        'shared_by', 'month', 'year',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function sharedBy()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}