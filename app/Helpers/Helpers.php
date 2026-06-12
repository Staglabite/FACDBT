<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\HITUAM01\HITUAM_MSHUSER as User;

class Helpers
{
    /**
     * Generate menu attributes for semi-dark mode
     *
     * @param  bool  $semiDarkEnabled  Whether semi-dark mode is enabled
     * @return array HTML attributes for the menu element
     */
    public static function getMenuAttributes($semiDarkEnabled)
    {
        $attributes = [];

        if ($semiDarkEnabled) {
            $attributes['data-bs-theme'] = 'dark';
        }

        return $attributes;
    }

    public static function appClasses()
    {

        $data = config('custom.custom');

        // default data array
        $DefaultData = [
            'myLayout' => 'vertical',
            'myTheme' => 'light',
            'mySkins' => 'default',
            'hasSemiDark' => false,
            'myRTLMode' => true,
            'hasCustomizer' => true,
            'showDropdownOnHover' => true,
            'displayCustomizer' => true,
            'contentLayout' => 'wide',
            'headerType' => 'fixed',
            'navbarType' => 'sticky',
            'menuFixed' => true,
            'menuCollapsed' => false,
            'footerFixed' => false,
            'customizerControls' => [
                'color',
                'theme',
                'skins',
                'semiDark',
                'layoutCollapsed',
                'layoutNavbarOptions',
                'headerType',
                'contentLayout',
                'rtl',
            ],
            //   'defaultLanguage'=>'en',
        ];

        // if any key missing of array from custom.php file it will be merge and set a default value from dataDefault array and store in data variable
        $data = array_merge($DefaultData, $data);

        // All options available in the template
        $allOptions = [
            'myLayout' => ['vertical', 'horizontal', 'blank', 'front'],
            'menuCollapsed' => [true, false],
            'hasCustomizer' => [true, false],
            'showDropdownOnHover' => [true, false],
            'displayCustomizer' => [true, false],
            'contentLayout' => ['compact', 'wide'],
            'headerType' => ['fixed', 'static'],
            'navbarType' => ['sticky', 'static', 'hidden'],
            'myTheme' => ['light', 'dark', 'system'],
            'mySkins' => ['default', 'bordered', 'raspberry'],
            'hasSemiDark' => [true, false],
            'myRTLMode' => [true, false],
            'menuFixed' => [true, false],
            'footerFixed' => [true, false],
            'customizerControls' => [],
            // 'defaultLanguage'=>array('en'=>'en','fr'=>'fr','de'=>'de','ar'=>'ar'),
        ];

        // if myLayout value empty or not match with default options in custom.php config file then set a default value
        foreach ($allOptions as $key => $value) {
            if (array_key_exists($key, $DefaultData)) {
                if (gettype($DefaultData[$key]) === gettype($data[$key])) {
                    // data key should be string
                    if (is_string($data[$key])) {
                        // data key should not be empty
                        if (isset($data[$key]) && $data[$key] !== null) {
                            // data key should not be exist inside allOptions array's sub array
                            if (! array_key_exists($data[$key], $value)) {
                                // ensure that passed value should be match with any of allOptions array value
                                $result = array_search($data[$key], $value, 'strict');
                                if (empty($result) && $result !== 0) {
                                    $data[$key] = $DefaultData[$key];
                                }
                            }
                        } else {
                            // if data key not set or
                            $data[$key] = $DefaultData[$key];
                        }
                    }
                } else {
                    $data[$key] = $DefaultData[$key];
                }
            }
        }
        $themeVal = $data['myTheme'] == 'dark' ? 'dark' : 'light';
        $themeUpdatedVal = $data['myTheme'] == 'dark' ? 'dark' : $data['myTheme'];

        // Determine if the layout is admin or front based on template name
        $layoutName = $data['myLayout'];
        $isAdmin = ! Str::contains($layoutName, 'front');

        $modeCookieName = $isAdmin ? 'admin-mode' : 'front-mode';
        $colorPrefCookieName = $isAdmin ? 'admin-colorPref' : 'front-colorPref';
        $primaryColorCookieName = $isAdmin ? 'admin-primaryColor' : 'front-primaryColor';

        // Get primary color from custom.php if explicitly set
        $primaryColor = null;
        if (array_key_exists('primaryColor', $data)) {
            $primaryColor = $data['primaryColor'];
        }

        // Check for primary color in cookie
        if (isset($_COOKIE[$primaryColorCookieName])) {
            $primaryColor = $_COOKIE[$primaryColorCookieName];
        }

        // Determine style based on cookies, only if not 'blank-layout'
        if ($layoutName !== 'blank') {
            if (isset($_COOKIE[$modeCookieName])) {
                $themeVal = $_COOKIE[$modeCookieName];
                if ($themeVal === 'system') {
                    $themeVal = isset($_COOKIE[$colorPrefCookieName]) ? $_COOKIE[$colorPrefCookieName] : 'light';
                }
                $themeUpdatedVal = $_COOKIE[$modeCookieName];
            }
        }

        // Define standardized cookie names
        $skinCookieName = 'customize_skin';
        $semiDarkCookieName = 'customize_semi_dark';

        // Process skin and semi-dark settings only for admin layouts
        if ($isAdmin) {
            // Get skin from cookie or fall back to config
            $skinFromCookie = isset($_COOKIE[$skinCookieName]) ? $_COOKIE[$skinCookieName] : null;
            $configSkin = isset($data['mySkins']) ? $data['mySkins'] : 'default';
            $skinName = $skinFromCookie ?: $configSkin;

            // Get semi-dark setting from cookie or fall back to config
            $semiDarkFromCookie = isset($_COOKIE[$semiDarkCookieName]) ? $_COOKIE[$semiDarkCookieName] : null;
            // Ensure we have a proper boolean conversion
            $semiDarkEnabled = $semiDarkFromCookie !== null ?
                filter_var($semiDarkFromCookie, FILTER_VALIDATE_BOOLEAN) :
                (bool) $data['hasSemiDark'];
        } else {
            // For front-end layouts, use defaults
            $skinName = 'default';
            $semiDarkEnabled = false;
        }

        // Get menu Collapsed state from cookie or fall back to config
        $menuCollapsedFromCookie = isset($_COOKIE['LayoutCollapsed']) ? $_COOKIE['LayoutCollapsed'] : $data['menuCollapsed'];

        // Get content layout from cookie or fall back to config
        $contentLayoutFromCookie = isset($_COOKIE['contentLayout']) ? $_COOKIE['contentLayout'] : $data['contentLayout'];

        // Get header type from cookie or fall back to config
        $navbarTypeFromCookie = isset($_COOKIE['navbarType']) ? $_COOKIE['navbarType'] : $data['navbarType'];

        // Get Header type from cookie or fall back to config
        $headerTypeFromCookie = isset($_COOKIE['headerType']) ? $_COOKIE['headerType'] : $data['headerType'];

        $directionVal = isset($_COOKIE['direction']) ? ($_COOKIE['direction'] === 'true' ? 'rtl' : 'ltr') : $data['myRTLMode'];

        // layout classes
        $layoutClasses = [
            'layout' => $data['myLayout'],
            'skins' => $data['mySkins'],
            'skinName' => $skinName,
            'semiDark' => $semiDarkEnabled,
            'color' => $primaryColor,
            'theme' => $themeVal,
            'themeOpt' => $data['myTheme'],
            'themeOptVal' => $themeUpdatedVal,
            'rtlMode' => $data['myRTLMode'],
            'textDirection' => $directionVal,
            'menuCollapsed' => $menuCollapsedFromCookie,
            'hasCustomizer' => $data['hasCustomizer'],
            'showDropdownOnHover' => $data['showDropdownOnHover'],
            'displayCustomizer' => $data['displayCustomizer'],
            'contentLayout' => $contentLayoutFromCookie,
            'headerType' => $headerTypeFromCookie,
            'navbarType' => $navbarTypeFromCookie,
            'menuFixed' => $data['menuFixed'],
            'footerFixed' => $data['footerFixed'],
            'customizerControls' => $data['customizerControls'],
            'menuAttributes' => self::getMenuAttributes($semiDarkEnabled),
        ];

        // sidebar Collapsed
        if ($layoutClasses['menuCollapsed'] === 'true' || $layoutClasses['menuCollapsed'] === true) {
            $layoutClasses['menuCollapsed'] = 'layout-menu-collapsed';
        } else {
            $layoutClasses['menuCollapsed'] = '';
        }

        // Header Type
        if ($layoutClasses['headerType'] == 'fixed') {
            $layoutClasses['headerType'] = 'layout-menu-fixed';
        }
        // Navbar Type
        if ($layoutClasses['navbarType'] == 'sticky') {
            $layoutClasses['navbarType'] = 'layout-navbar-fixed';
        } elseif ($layoutClasses['navbarType'] == 'static') {
            $layoutClasses['navbarType'] = '';
        } else {
            $layoutClasses['navbarType'] = 'layout-navbar-hidden';
        }

        // Menu Fixed
        if ($layoutClasses['menuFixed'] == true) {
            $layoutClasses['menuFixed'] = 'layout-menu-fixed';
        }

        // Footer Fixed
        if ($layoutClasses['footerFixed'] == true) {
            $layoutClasses['footerFixed'] = 'layout-footer-fixed';
        }

        // RTL Layout/Mode
        if ($layoutClasses['rtlMode'] == true) {
            $layoutClasses['rtlMode'] = 'rtl';
            $layoutClasses['textDirection'] = isset($_COOKIE['direction']) ? ($_COOKIE['direction'] === 'true' ? 'rtl' : 'ltr') : 'rtl';
        } else {
            $layoutClasses['rtlMode'] = 'ltr';
            $layoutClasses['textDirection'] = isset($_COOKIE['direction']) && $_COOKIE['direction'] === 'true' ? 'rtl' : 'ltr';
        }

        // Show DropdownOnHover for Horizontal Menu
        if ($layoutClasses['showDropdownOnHover'] == true) {
            $layoutClasses['showDropdownOnHover'] = true;
        } else {
            $layoutClasses['showDropdownOnHover'] = false;
        }

        // To hide/show display customizer UI, not js
        if ($layoutClasses['displayCustomizer'] == true) {
            $layoutClasses['displayCustomizer'] = true;
        } else {
            $layoutClasses['displayCustomizer'] = false;
        }

        return $layoutClasses;
    }

    public static function updatePageConfig($pageConfigs)
    {
        $demo = 'custom';
        if (isset($pageConfigs)) {
            if (count($pageConfigs) > 0) {
                foreach ($pageConfigs as $config => $val) {
                    Config::set('custom.' . $demo . '.' . $config, $val);
                }
            }
        }
    }

    /**
     * Generate CSS for primary color
     *
     * @param  string  $color  Hex color code for primary color
     * @return string CSS for primary color
     */
    public static function generatePrimaryColorCSS($color)
    {
        if (! $color) {
            return '';
        }

        // Check if the color actually came from a cookie or explicit configuration
        // Don't generate CSS if there's no specific need for a custom color
        $configColor = config('custom.custom.primaryColor', null);
        $isFromCookie = isset($_COOKIE['admin-primaryColor']) || isset($_COOKIE['front-primaryColor']);

        if (! $configColor && ! $isFromCookie) {
            return '';
        }

        $r = hexdec(substr($color, 1, 2));
        $g = hexdec(substr($color, 3, 2));
        $b = hexdec(substr($color, 5, 2));

        // Calculate contrast color based on YIQ formula
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        $contrastColor = ($yiq >= 150) ? '#000' : '#fff';

        return <<<CSS
        :root, [data-bs-theme=light], [data-bs-theme=dark] {
        --bs-primary: {$color};
        --bs-primary-rgb: {$r}, {$g}, {$b};
        --bs-primary-bg-subtle: rgba({$r}, {$g}, {$b}, 0.1);
        --bs-primary-border-subtle: rgba({$r}, {$g}, {$b}, 0.3);
        --bs-primary-contrast: {$contrastColor};
        }
        CSS;
    }

    // untuk mengambil supplier id yang login
    public static function getSupplierId()
    {
        $roles = count(Auth::user()->roles) > 0 ? Auth::user()->roles->pluck('VROLENAME')->toArray()[0] : 'Guest';

        $userSupplier = Auth::user()->load(['supplierUser'])->supplierUser;

        // $isAdmin = $roles === 'Admin' || $userSupplier === null;

        // $idViewers = $isAdmin ? 0 : ($userSupplier == null ? 0 : $userSupplier->ISUPPLIER_ID);

        // $user = User::find(Auth::user()->IID);

        // dd($userSupplier->ISUPPLIER_ID);

        return $userSupplier->ISUPPLIER_ID ?? NULL;
    }

    public static function listDateFormat($tglInvoice)
    {
        $invoiceDateFormats = [];

        if ($tglInvoice) {
            // pastikan input: 14-01-2026
            $tglInvoice = Carbon::createFromFormat('Y-m-d', trim($tglInvoice));

            $invoiceDateFormats = [
                $tglInvoice->format('d-m-Y'), // 14-01-2026
                // $tglInvoice->format('d/m/Y'), // 14/01/2026
                // $tglInvoice->format('m/d/Y'), // 01/14/2026
                // $tglInvoice->format('Y-m-d'), // 2026-01-14
                // $tglInvoice->format('d-M-Y'), // 14 Jan 2026
                // $tglInvoice->format('Y-M-d'), // 14 Jan 2026
                // $tglInvoice->format('M-d-Y'), // 14 Jan 2026
                // $tglInvoice->format('d/M/Y'), // 14 Jan 2026
                // $tglInvoice->format('Y/M/d'), // 14 Jan 2026
                // $tglInvoice->format('M/d/Y'), // 14 Jan 2026
                // $tglInvoice->format('d M Y'), // 14 Jan 2026
                // $tglInvoice->format('d F Y'), // 09 January 2026
                // $tglInvoice->format('j F Y'), // 9 January 2026
                // $tglInvoice->format('F d. Y'), // January 14. 2026
                // $tglInvoice->format('j-n-Y'),   // 14-1-2026
                // $tglInvoice->format('j/n/Y'),   // 14/1/2026
                // $tglInvoice->format('n/j/Y'),   // 1/14/2026
                // $tglInvoice->format('Y-n-j'),   // 2026-1-14
                // $tglInvoice->format('d-m-y'), // 14-01-26
                // $tglInvoice->format('d/m/y'), // 14/01/26
                // $tglInvoice->format('y-m-d'), // 26-01-14
                // $tglInvoice->format('d-M-y'), // 26-Sep-2026
                // $tglInvoice->format('y-M-d'), // 2026-Sep-26
                // $tglInvoice->format('M-y-d'), // Sep/2026/26
                // $tglInvoice->format('m/d/y'), // 01/14/26
                // $tglInvoice->format('j-m-Y'), // 4-1-2026
                // $tglInvoice->format('j/m/Y'), // 4/1/2026
                // $tglInvoice->format('n/j/Y'), // 1/4/2026
                // $tglInvoice->format('Y/j/n'), // 2026/4/1
                // $tglInvoice->format('dmY'), // 14012026
                // $tglInvoice->format('Ymd'), // 20260114
                // $tglInvoice->format('dmy'), // 140126
                // $tglInvoice->format('ymd'), // 260114
                // $tglInvoice->format('d.m.Y'), // 14.01.2026
                // $tglInvoice->format('d.m.y'), // 14.01.26
                // $tglInvoice->format('d M, Y'), // 14 Jan, 2026
                // $tglInvoice->format('M d, Y'), // Jan 14, 2026
                // strtoupper($tglInvoice->format('Y-M-d')), // 14 JAN 2026
                // strtoupper($tglInvoice->format('M-d-Y')), // 14 JAN 2026
                // strtoupper($tglInvoice->format('d-M-Y')), // 14 JAN 2026
                // strtoupper($tglInvoice->format('d M Y')), // 14 JAN 2026
                // strtoupper($tglInvoice->format('Y M d')), // 14 JAN 2026
                // strtoupper($tglInvoice->format('M d Y')), // 14 JAN 2026
                // strtoupper($tglInvoice->format('d F Y')), // 14 JANUARY 2026
                // self::formatTanggalIndo($tglInvoice, 'Y F d'),
                // self::formatTanggalIndo($tglInvoice, 'F d Y'),
                // self::formatTanggalIndo($tglInvoice, 'd F Y'),
                // strtoupper(self::formatTanggalIndo($tglInvoice, 'd F Y')), // 14 JANUARI 2026
                // strtoupper(self::formatTanggalIndo($tglInvoice, 'd, F Y')), // 14 JANUARI 2026
                // strtoupper(self::formatTanggalIndo($tglInvoice, 'd-F-Y')), // 14 JANUARI 2026
                // self::formatTanggalIndo($tglInvoice, 'd-m-Y'), // 14-Januari-2026
                // self::formatTanggalIndo($tglInvoice, 'd-M-Y'), // 14-Januari-2026
                // self::formatTanggalIndo($tglInvoice, 'd/m/Y'), // 14/Januari/2026
                // self::formatTanggalIndo($tglInvoice, 'j F Y'), // 4 Januari 2026
                // self::formatTanggalIndo($tglInvoice, 'd, F Y'), // 4 Januari 2026
            ];
        }

        return $invoiceDateFormats;
    }

    public static function formatTanggalIndo($tanggal, string $format = 'd F Y')
    {
        if (!$tanggal) {
            return null;
        }

        try {
            Carbon::setLocale('id');

            return Carbon::parse($tanggal)
                ->translatedFormat($format);
        } catch (\Exception $e) {
            return null;
        }
    }
}
