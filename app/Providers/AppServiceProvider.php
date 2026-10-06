<?php

namespace App\Providers;

use App\Helpers\EnvOverride;
use App\Models\DtrSetting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        if (app()->environment('production') || env('APP_ENV') === 'production') {
            EnvOverride::apply();
        }

        View::composer('*', function ($view) {
            $view->with('settings', DtrSetting::getSettings());
        });
    }
}
