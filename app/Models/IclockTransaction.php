<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IclockTransaction extends Model
{
    use HasFactory;

    protected $connection = 'zkbiotime';
    protected $table = 'iclock_transaction';
    public $timestamps = false;

    protected $dates = [
        'punch_time', 'upload_time', 'sync_time',
    ];

    protected $fillable = [
        'emp_code', 'punch_time', 'punch_state', 'verify_type',
        'work_code', 'terminal_sn', 'terminal_alias', 'area_alias',
        'longitude', 'latitude', 'gps_location', 'mobile',
        'source', 'purpose', 'crc', 'is_attendance', 'reserved',
        'upload_time', 'sync_status', 'sync_time', 'is_mask',
        'temperature', 'emp_id', 'terminal_id', 'company_code',
    ];

    public function scopeForEmployee($query, $empCode)
    {
        return $query->where('emp_code', $empCode);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereDate('punch_time', '>=', $startDate)
                     ->whereDate('punch_time', '<=', $endDate);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('punch_time');
    }
}
