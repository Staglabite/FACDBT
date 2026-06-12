@php
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Request;
    $path = Request::path();
    $currentRouteName = Route::currentRouteName();
@endphp

<ul class="menu-sub">
    @if (isset($menu))
        @foreach ($menu as $submenu)
            {{-- active menu method --}}
            @php
                $activeClass = null;
                $active = $configData['layout'] === 'vertical' ? 'active open' : 'active';

                // Check if current route matches
                if (
                    (isset($submenu->VURL) && $path === $submenu->VURL) ||
                    (isset($submenu->VURL) && $currentRouteName === $submenu->VURL)
                ) {
                    $activeClass = 'active';
                }
                // Check if any child is active
                elseif (isset($submenu->children) && $submenu->children->count() > 0) {
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

                    if ($checkActive($submenu->children)) {
                        $activeClass = $active;
                    }
                }

                $submenuName = $submenu->VAPPDESC ?? ($submenu->VDESC ?? '');
                $submenuSsoTargetUrl = filled($ssoTargetUrl ?? null)
                    ? $ssoTargetUrl
                    : (filled($submenu->VENVAPP) && strtolower((string) $submenu->VTYPEAPP) === 'sso'
                        ? $submenu->VENVAPP
                        : null);
                $submenuHref = isset($submenu->children) && $submenu->children->count() > 0
                    ? 'javascript:void(0)'
                    : (filled($submenuSsoTargetUrl) && filled($submenu->VURL)
                        ? route('auth.sso.send', [
                            'target_url' => $submenuSsoTargetUrl,
                            'redirect' => $submenu->VURL,
                        ])
                        : (isset($submenu->VURL) && !empty($submenu->VURL) ? url($submenu->VURL) : 'javascript:void(0)'));
            @endphp

            <li class="menu-item {{ $activeClass }}">
                <a href="{{ $submenuHref }}"
                    class="p-2 {{ isset($submenu->children) && $submenu->children->count() > 0 ? 'menu-link menu-toggle' : 'menu-link' }}">
                    @if (isset($submenu->VICON) && !empty($submenu->VICON))
                        <i class="menu-icon me-2 icon-base ti tabler-{{ $submenu->VICON }}"></i>
                    @endif
                    <div>{{ $submenuName }}</div>
                </a>

                {{-- submenu --}}
                @if (isset($submenu->children) && $submenu->children->count() > 0)
                    @include('layouts.sections.menu.submenu', [
                        'menu' => $submenu->children,
                        'ssoTargetUrl' => $ssoTargetUrl ?? null,
                    ])
                @endif
            </li>
        @endforeach
    @endif
</ul>
