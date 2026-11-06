<?php

namespace App\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        if (!App::runningInConsole()) {
            $theme = theme_root_path();
            $path = base_path('resources/themes/' . $theme);
            if (!defined('VIEW_FILE_NAMES')) {
                define("VIEW_FILE_NAMES", include($path . '/file_names.php'));
            }
            view()->addLocation($path);
            if ($theme === 'theme_sigma_care') {
                view()->addLocation(base_path('resources/themes/theme_aster'));
                // Some shared account, refund and support controllers still use Default's view names.
                // Care bridges their layout and assets while retaining those existing forms and actions.
                view()->addLocation(base_path('resources/themes/default'));
            }
        }
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

    }
}
