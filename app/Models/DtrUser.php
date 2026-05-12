<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DtrUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'emp_code', 'first_name', 'last_name', 'middle_name',
        'position', 'department', 'office', 'section', 'office_id', 'section_id',
        'employee_status', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function officeModel()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function sectionModel()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function getFullNameAttribute()
    {
        $middle = $this->middle_name ? ' ' . strtoupper(substr($this->middle_name, 0, 1)) . '.' : '';
        $name = trim($this->first_name . $middle . ' ' . $this->last_name);
        return $name ?: 'Employee #' . $this->emp_code;
    }
}
