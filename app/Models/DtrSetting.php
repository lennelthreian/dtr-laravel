<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DtrSetting extends Model
{
    use HasFactory;

    protected $fillable = ['setting_key', 'setting_value'];

    public static function getSettings(): array
    {
        return self::pluck('setting_value', 'setting_key')->toArray();
    }
}
