<?php

namespace App\Services\HITUAM;

use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;

class MenuService
{
    public function getAllowedMenusForRole(array $roleIds)
    {
        sort($roleIds);
        $cacheKey = 'allowed_menus_for_role#' . implode('_', $roleIds);

        $applications = Application::query()
            ->with(['menus' => function ($query) use ($roleIds) {
                $query->whereHas('accesses', function ($query) use ($roleIds) {
                    $query->whereIn('HITUAM_MSHROLEACCESS.VROLE', $roleIds);
                })->orderBy('NSORTAPP');
                // ->orderBy('NSORTPROJECT');
            }])
            ->whereHas('menus', function ($query) use ($roleIds) {
                $query->whereHas('accesses', function ($query) use ($roleIds) {
                    $query->whereIn('HITUAM_MSHROLEACCESS.VROLE', $roleIds);
                })->orderBy('NSORTAPP');
            })
            ->orderBy('NORDERPROJECT')
            ->get();

        foreach ($applications as $application) {
            $application->children = $this->tree($application->menus);
        }

        return $applications;

        // return Cache::remember($cacheKey, now()->addHour(2), function () use ($roleIds) {
        // });
    }

    private function tree($menus)
    {
        // Menu hierarchy is driven by VPARENT, not IPARENT_ID.
        // Build the tree from root menus first, then attach descendants recursively.
        $root = $menus
            ->filter(fn($menu) => blank($menu->VPARENT))
            ->sortBy('NSORTAPP')
            ->values();

        $this->formatTree($root, $menus);

        return $root->groupBy('VFLAG');
    }

    private function formatTree($menus, $allMenus)
    {
        foreach ($menus as $menu) {
            $children = $allMenus->where('VPARENT', $menu->VAPPID)
                ->sortBy('NSORTAPP')
                ->values();

            $menu->children = $children;

            if ($children->count() > 0) {
                $this->formatTree($children, $allMenus);
            }
        }
    }

    public function getApplications(Request $request)
    {
        $applications = Application::filtered($request)->get();

        return $applications;
    }

    public function getMenus(Request $request)
    {
        $menus = Menu::filtered($request)->get();

        return $menus;
    }

    public function getAllMenus()
    {
        $menus = Application::with('menus.services')->get();

        return $menus;
    }
}
