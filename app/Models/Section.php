<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\LogsUserActivity;

class Section extends Model
{
    use LogsUserActivity;
    protected $fillable = ['office_id', 'name', 'supervisor_id', 'oic_id'];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function dtrUsers()
    {
        return $this->hasMany(DtrUser::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(DtrUser::class, 'supervisor_id');
    }

    public function oic()
    {
        return $this->belongsTo(DtrUser::class, 'oic_id');
    }
}
