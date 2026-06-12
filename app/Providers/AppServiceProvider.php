<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (app()->environment('staging', 'production')) {
            $this->app['request']->server->set('HTTPS', true);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('staging', 'production')) {
            URL::forceScheme('https');
        }
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Vite::useStyleTagAttributes(function (?string $src, string $url, ?array $chunk, ?array $manifest) {
            if ($src !== null) {
                return [
                    'class' => preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?core)-?.*/i", $src) ? 'template-customizer-core-css' : (preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?theme)-?.*/i", $src) ? 'template-customizer-theme-css' : ''),
                ];
            }

            return [];
        });

        Blade::if('serve', function ($expression) {
            $user = Auth::user();

            if (! $user) {
                return false;
            }

            return in_array($expression, $user->serviceNames());
        });
    }
}
