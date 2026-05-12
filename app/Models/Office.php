<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $fillable = ['name', 'supervisor_id', 'senior_manager_id'];

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
}
