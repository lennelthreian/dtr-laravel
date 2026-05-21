<?php

namespace App\Services;

trait LogsUserActivity
{
    protected static function bootLogsUserActivity()
    {
        static::created(function ($model) {
            app(UserLogService::class)->created(
                auth()->id(),
                get_class($model),
                $model->id,
                null,
                $model->getDirty()
            );
        });

        static::updated(function ($model) {
            $old = [];
            $new = [];
            foreach ($model->getDirty() as $key => $value) {
                $old[$key] = $model->getOriginal($key);
                $new[$key] = $value;
            }

            if (!empty($new)) {
                app(UserLogService::class)->updated(
                    auth()->id(),
                    get_class($model),
                    $model->id,
                    null,
                    $old,
                    $new
                );
            }
        });

        static::deleted(function ($model) {
            app(UserLogService::class)->deleted(
                auth()->id(),
                get_class($model),
                $model->id,
                null,
                $model->getAttributes()
            );
        });
    }
}
