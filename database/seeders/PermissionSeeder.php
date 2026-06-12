<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HITUAM01\HITUAM_MSHSERVICE as Service;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [];

        $menus = Menu::all();
        $applications = Application::all();

        //manual add data for services at hituamf006 because the page load hituamf004 and hituamf005
        // $menus = $menus->merge([
        //     new Menu([
        //         'IID'      => 1,
        //         'VAPPID'   => 'HITUAMF004',
        //         'VAPPDESC' => 'Role',
        //     ]),
        // ]);

        foreach ($menus as $menu) {
            $mapPermission = $this->mapPermission($menu->VAPPID);

            // Get application ID from menu's NSORTPROJECT
            $application = Application::find($menu->NSORTPROJECT);
            $applicationDesc = $application?->VPROJECTDESC;

            foreach ($mapPermission as $perm) {
                $url = strtolower($menu->VAPPDESC) . '/' . strtolower($perm);
                $services[] = [
                    "VNAME" => "{$menu->VAPPID}-{$perm}",
                    "VDESC" => "{$menu->VAPPDESC} {$perm}",
                    "VURL" => $url,
                    "VMETHOD" => $this->getMethod($perm),
                    "DBEGINEFF" => '2025-01-01',
                    "DENDEFF" => '2999-01-01',
                    "VMENUID" => $menu->VAPPID,
                    "VAPPLICATION_DESC" => $applicationDesc,
                    "VCREA" => 'Seeder',
                    "DCREA" => now()
                ];
            }

            if ($menu->VAPPID == 'HITUAMF006') {
                foreach (['Create', 'Update', 'Delete'] as $perm) {
                    $services[] = [
                        "VNAME" => "HITUAMF004-{$perm}",
                        "VDESC" => "Role {$perm}",
                        "VURL" =>  'role' . '/' . strtolower($perm),
                        "VMETHOD" => $this->getMethod($perm),
                        "DBEGINEFF" => '2025-01-01',
                        "DENDEFF" => '2999-01-01',
                        "VMENUID" => $menu->VAPPID,
                        "VAPPLICATION_DESC" => $applicationDesc,
                        "VCREA" => 'Seeder',
                        "DCREA" => now()
                    ];

                    $services[] = [
                        "VNAME" => "HITUAMF005-{$perm}",
                        "VDESC" => "User {$perm}",
                        "VURL" =>  'user' . '/' . strtolower($perm),
                        "VMETHOD" => $this->getMethod($perm),
                        "DBEGINEFF" => '2025-01-01',
                        "DENDEFF" => '2999-01-01',
                        "VMENUID" => $menu->VAPPID,
                        "VAPPLICATION_DESC" => $applicationDesc,
                        "VCREA" => 'Seeder',
                        "DCREA" => now()
                    ];
                }
            }
        }


        Service::truncate();
        Service::insert($services);
    }
    private function mapPermission(string $appId): array
    {
        $permissionMap = [
            'PPCSPMF001' => ['Create', 'Update', 'Delete'], // Application
            'HITUAMF001' => ['Create', 'Update', 'Delete'], // Application
            'HITUAMF002' => ['Create', 'Update', 'Delete'], // Menu
            'HITUAMF003' => ['Create', 'Update', 'Delete'], // Service
            'HITUAMF004' => ['Create', 'Update', 'Delete'], // Role
            'HITUAMF010' => ['Create', 'Update', 'Delete'], // Configurations
            'HITUAMF011' => ['Create', 'Update', 'Delete', 'View'], // News
            // 'HITUAMF006' => ['Create', 'Update', 'Delete'], // Role
            'FACTWMF001' => ['Create', 'Update', 'Delete'], // Configuration
            'FACTWMF002' => ['Create', 'Update', 'Delete'], // Supplier
            'FACTWMF003' => ['Create', 'Update', 'Delete'], // Change Request Supplier
            'FACTWMF004' => ['Create', 'Update', 'Delete', 'View'], // News
            'FACTWMF005' => ['Create', 'Update', 'Delete'], // Information
            'FACTWMF006' => ['Create', 'Dispute', 'Approval'], // GR Notes
            'FACTWMF007' => ['Create', 'Update', 'Delete'],
            'FACTWMF008' => ['Create', 'Update', 'Delete'],
            'FACTWMF013' => ['Create'], // DMS
        ];

        return $permissionMap[$appId] ?? [];
    }


    private function getMethod($action)
    {
        $method = '';

        switch ($action) {
            case 'Update':
                $method = 'PATCH';
                break;
            case 'Delete':
                $method = 'DELETE';
                break;
            default:
                $method = 'POST';
                break;
        }

        return $method;
    }
}
