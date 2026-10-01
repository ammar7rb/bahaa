<?php

namespace App\Providers;

use App\Models\BusinessSetting;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $timezone = config('app.timezone', 'Africa/Cairo');
        try {
            $setting = BusinessSetting::where(['type' => 'timezone'])->value('value');
            if (is_string($setting) && in_array($setting, \DateTimeZone::listIdentifiers(), true)) {
                $timezone = $setting;
            }
        } catch (Exception $ex) {}
        Config::set('app.timezone', $timezone);
        Config::set('timezone', $timezone);
        date_default_timezone_set($timezone);
    }
}
