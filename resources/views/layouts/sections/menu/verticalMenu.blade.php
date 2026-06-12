@php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
$configData = Helper::appClasses();
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

    <!-- ! Hide app brand if navbar-full -->
    @if (!isset($navbarFull))
    <div class="app-brand demo">
        <a href="{{ route('hituam.news.index') }}" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img width="30" class="" src="{{ asset('assets/img/point-logo-shock.png') }}" alt="">
            </span>
            <span class="app-brand-text demo menu-text fw-bold">
                <img width="160" height="110" class="" src="{{ asset('assets/img/point-logo-single.png') }}"
                    alt="">
            </span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="icon-base ti menu-toggle-icon d-none d-xl-block"></i>
            <i class="icon-base ti tabler-x d-block d-xl-none"></i>
        </a>
    </div>
    @endif

    <div class="menu-inner-shadow"></div>
    <div class="menu-content flex-grow-1 overflow-auto">
        <ul class="menu-inner py-1">
            @foreach ($menuData as $application)
            @php
            $activeClass = null;
            $currentRouteName = Route::currentRouteName();
            $path = Request::path();
            $applicationName = $application->VPORTALNAME ?? ($application->VPROJECTDESC ?? '');
            $hasApplicationChildren = isset($application->children) && count($application->children) > 0;
            $applicationIsExternal = $application->isExternal();
            $applicationSsoTargetUrl = $applicationIsExternal ? $application->VHOST : null;
            $applicationHref = $applicationIsExternal && ! $hasApplicationChildren
                ? route('auth.sso.send', ['target_url' => $application->VHOST])
                : 'javascript:void(0);';

            // Check if any child menu is active
            if (isset($application->children)) {
            foreach ($application->children as $flagGroup) {
            foreach ($flagGroup as $menu) {
            if (
            (isset($menu->VURL) && $path === $menu->VURL) ||
            (isset($menu->VURL) && $currentRouteName === $menu->VURL)
            ) {
            $activeClass = 'active open';
            break 2;
            } elseif (isset($menu->children) && $menu->children->count() > 0) {
            // Check nested children recursively
            $checkActive = function ($items) use ($currentRouteName, $path, &$checkActive) {
            foreach ($items as $item) {
            if (
            (isset($item->VURL) && $path === $item->VURL) ||
            (isset($item->VURL) && $currentRouteName === $item->VURL)
            ) {
            return true;
            }
            if (isset($item->children) && $item->children->count() > 0) {
            if ($checkActive($item->children)) {
            return true;
            }
            }
            }
            return false;
            };

            if ($checkActive($menu->children)) {
            $activeClass = 'active open';
            break 2;
            }
            }
            }
            }
            }
            @endphp

            @if ($hasApplicationChildren)
            <li class="menu-item {{ $activeClass }}">
                <a href="{{ $applicationHref }}" class="menu-link menu-toggle">
                    @if (isset($application->VICON) && !empty($application->VICON))
                    <i class="menu-icon icon-base ti tabler-{{ $application->VICON }}"></i>
                    @endif
                    <div>{{ $applicationName }}</div>
                </a>

                {{-- Application's menus grouped by VFLAG --}}
                <ul class="menu-sub">
                    @foreach ($application->children as $flag => $menus)
                    {{-- Menu header for VFLAG --}}
                    @if ($flag)
                    <li class="menu-header small">
                        <span class="menu-header-text">{{ __($flag) }}</span>
                    </li>
                    @endif

                    {{-- Menus under this flag --}}
                    @foreach ($menus as $menu)
                    @php
                    $menuActiveClass = null;
                    $active = $configData['layout'] === 'vertical' ? 'active open' : 'active';

                    if (
                    (isset($menu->VURL) && $path === $menu->VURL) ||
                    (isset($menu->VURL) && $currentRouteName === $menu->VURL)
                    ) {
                    $menuActiveClass = 'active';
                    } elseif (isset($menu->children) && $menu->children->count() > 0) {
                    $checkActive = function ($items) use (
                    $currentRouteName,
                    $path,
                    &$checkActive,
                    ) {
                    foreach ($items as $item) {
                    if (
                    (isset($item->VURL) && $path === $item->VURL) ||
                    (isset($item->VURL) && $currentRouteName === $item->VURL)
                    ) {
                    return true;
                    }
                    if (isset($item->children) && $item->children->count() > 0) {
                    if ($checkActive($item->children)) {
                    return true;
                    }
                    }
                    }
                    return false;
                    };

                    if ($checkActive($menu->children)) {
                    $menuActiveClass = $active;
                    }
                    }

                    $menuName = $menu->VAPPDESC ?? ($menu->VDESC ?? '');
                    $menuSsoTargetUrl = filled($applicationSsoTargetUrl)
                        ? $applicationSsoTargetUrl
                        : (filled($menu->VENVAPP) && strtolower((string) $menu->VTYPEAPP) === 'sso'
                            ? $menu->VENVAPP
                            : null);
                    $menuHref = isset($menu->children) && $menu->children->count() > 0
                        ? 'javascript:void(0)'
                        : (filled($menuSsoTargetUrl) && filled($menu->VURL)
                            ? route('auth.sso.send', [
                                'target_url' => $menuSsoTargetUrl,
                                'redirect' => $menu->VURL,
                            ])
                            : (isset($menu->VURL) && !empty($menu->VURL) ? url($menu->VURL) : 'javascript:void(0)'));
                    @endphp

                    <li class="menu-item {{ $menuActiveClass }}">
                        <a href="{{ $menuHref }}"
                            class="p-2 {{ isset($menu->children) && $menu->children->count() > 0 ? 'menu-link menu-toggle' : 'menu-link' }}">
                            @if (isset($menu->VICON) && !empty($menu->VICON))
                            <i class="menu-icon me-2 icon-base ti tabler-{{ $menu->VICON }}"></i>
                            @endif

                            <div>{{ $menuName }}</div>
                        </a>

                        {{-- submenu --}}
                        @if (isset($menu->children) && $menu->children->count() > 0)
                        @include('layouts.sections.menu.submenu', [
                        'menu' => $menu->children,
                        'ssoTargetUrl' => $applicationSsoTargetUrl,
                        ])
                        @endif
                    </li>
                    @endforeach
                    @endforeach
                </ul>
            </li>
            @endif
            @endforeach
        </ul>
    </div>

    {{-- <div class="logout-container">
    <ul class="menu-inner py-1">
      <li class="menu-item">
        <a class="menu-link" href="{{ route('logout') }}"
    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
    <i class="menu-icon icon-base ti tabler-power"></i>
    <div>{{ __('Logout') }}</div>
    </a>
    </li>
    </ul>
    <form method="POST" id="logout-form" action="{{ route('logout') }}">
        @csrf
    </form>
    </div> --}}
</aside>
