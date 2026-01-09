<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    /**
     * Boot the trait
     */
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            AuditLog::log(
                AuditLog::ACTION_CREATE,
                $model,
                null,
                $model->getAttributes()
            );
        });

        static::updated(function ($model) {
            $oldValues = array_intersect_key(
                $model->getOriginal(),
                $model->getDirty()
            );
            $newValues = $model->getDirty();

            // Don't log if nothing changed
            if (empty($newValues)) {
                return;
            }

            AuditLog::log(
                AuditLog::ACTION_UPDATE,
                $model,
                $oldValues,
                $newValues
            );
        });

        static::deleted(function ($model) {
            AuditLog::log(
                AuditLog::ACTION_DELETE,
                $model,
                $model->getAttributes(),
                null
            );
        });
    }

    /**
     * Get audit logs for this model
     */
    public function auditLogs()
    {
        return AuditLog::forModel($this)->orderBy('created_at', 'desc')->get();
    }

    /**
     * Log custom action
     */
    public function logAction(string $action, ?array $oldValues = null, ?array $newValues = null): AuditLog
    {
        return AuditLog::log($action, $this, $oldValues, $newValues);
    }
}
