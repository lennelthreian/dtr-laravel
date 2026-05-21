<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Services\LogsUserActivity;

class DtrSetting extends Model
{
    use HasFactory;
    use LogsUserActivity;

    protected $fillable = ['setting_key', 'setting_value'];

    public static function getSettings(): array
    {
        return Cache::remember('dtr_settings', 1440, function () {
            return self::pluck('setting_value', 'setting_key')->toArray();
        });
    }

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('dtr_settings');
        });
        static::deleted(function () {
            Cache::forget('dtr_settings');
        });
    }
}
