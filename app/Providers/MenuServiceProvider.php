<?php

namespace App\Providers;

use App\Models\HITUAM01\HITUAM_MSHCONFIGURATION;
use App\Services\HITUAM\MenuService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class MenuServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('layouts.sections.menu.verticalMenu', function ($view) {
            if (Auth::check()) {
                $menuService = new MenuService;
                $roleIds = Auth::user()->roles->pluck('VROLENAME')->toArray();

                $allowedMenu = $menuService->getAllowedMenusForRole($roleIds);

                $view->with('menuData', $allowedMenu);
            } else {
                $view->with('menuData', collect());
            }
        });

        View::composer('layouts.sections.footer.footer', function ($view) {
            $config = HITUAM_MSHCONFIGURATION::whereIn('VVARIABLE', ['contact_phone_number', 'contact_email'])->pluck('VVALUE', 'VVARIABLE');
            $view->with('configData', $config);
        });
    }
}
