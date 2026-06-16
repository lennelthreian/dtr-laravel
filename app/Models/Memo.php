<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Memo extends Model
{
    protected $fillable = [
        'employee_id', 'issued_by', 'notes', 'month_year',
    ];

    public function employee()
    {
        return $this->belongsTo(DtrUser::class, 'employee_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}