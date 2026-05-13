<?php

namespace App\Providers;

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
        View::composer('*', function ($view) {
            $view->with('settings', DtrSetting::getSettings());
        });
    }
}
