<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\LogsUserActivity;

class Office extends Model
{
    use LogsUserActivity;
    protected $fillable = ['name', 'supervisor_id', 'senior_manager_id', 'oic_id', 'senior_manager_oic_id'];

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function dtrUsers()
    {
        return $this->hasMany(DtrUser::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(DtrUser::class, 'supervisor_id');
    }

    public function seniorManager()
    {
        return $this->belongsTo(DtrUser::class, 'senior_manager_id');
    }

    public function oic()
    {
        return $this->belongsTo(DtrUser::class, 'oic_id');
    }

    public function seniorManagerOic()
    {
        return $this->belongsTo(DtrUser::class, 'senior_manager_oic_id');
    }
}
