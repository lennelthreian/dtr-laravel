<?php

namespace App\Services;

use App\Models\UserLog;
use Illuminate\Http\Request;

class UserLogService
{
    public function log($userId, $action, $description = null, $entityType = null, $entityId = null, $oldValues = null, $newValues = null)
    {
        $data = [
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => $oldValues ? json_encode($oldValues) : null,
            'new_values'  => $newValues ? json_encode($newValues) : null,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ];

        return UserLog::create($data);
    }

    public function info($userId, $description, $entityType = null, $entityId = null)
    {
        return $this->log($userId, 'info', $description, $entityType, $entityId);
    }

    public function login($userId)
    {
        return $this->log($userId, 'login', 'User logged in');
    }

    public function logout($userId)
    {
        return $this->log($userId, 'logout', 'User logged out');
    }

    public function created($userId, $entityType, $entityId, $description = null, $newValues = null)
    {
        $description = $description ?? "Created {$entityType} #{$entityId}";
        return $this->log($userId, 'create', $description, $entityType, $entityId, null, $newValues);
    }

    public function updated($userId, $entityType, $entityId, $description = null, $oldValues = null, $newValues = null)
    {
        $description = $description ?? "Updated {$entityType} #{$entityId}";
        return $this->log($userId, 'update', $description, $entityType, $entityId, $oldValues, $newValues);
    }

    public function deleted($userId, $entityType, $entityId, $description = null, $oldValues = null)
    {
        $description = $description ?? "Deleted {$entityType} #{$entityId}";
        return $this->log($userId, 'delete', $description, $entityType, $entityId, $oldValues, null);
    }

    public function approved($userId, $entityType, $entityId, $description = null)
    {
        $description = $description ?? "Approved {$entityType} #{$entityId}";
        return $this->log($userId, 'approve', $description, $entityType, $entityId);
    }

    public function rejected($userId, $entityType, $entityId, $description = null)
    {
        $description = $description ?? "Rejected {$entityType} #{$entityId}";
        return $this->log($userId, 'reject', $description, $entityType, $entityId);
    }

    public function exported($userId, $entityType, $description = null)
    {
        $description = $description ?? "Exported {$entityType}";
        return $this->log($userId, 'export', $description, $entityType);
    }

    public function printed($userId, $entityType, $description = null)
    {
        $description = $description ?? "Printed {$entityType}";
        return $this->log($userId, 'print', $description, $entityType);
    }

    public function getUserLogs($userId = null, $action = null, $days = null, $limit = 50)
    {
        $query = UserLog::query();

        if ($userId) {
            $query->forUser($userId);
        }

        if ($action) {
            $query->byAction($action);
        }

        if ($days) {
            $query->recent($days);
        }

        return $query->latest('created_at')->limit($limit)->get();
    }
}
