<?php

namespace Database\Seeders;

use App\Enums\MenuFlag;
use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applicationHituam = Application::query()->where('VDEPT', 'HITUAM')->first();

        $hituamMenus = [
            [
                'VAPPID' => 'HITUAMF006',
                'VFLAG' => MenuFlag::BASIC,
                'VICON' => 'user-screen',
                'VAPPDESC' => 'Master User Role',
                'VURL' => 'HITUAM/bd/master-userrole',
                'VDESC' => 'Master User Role',
                'NSORTAPP' => 104,
                'VTYPEAPP' => 'LRVL-WEB',
                'NSORTPROJECT' => $applicationHituam->IID,
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => null,
            ],
            [
                'VAPPID' => 'HITUAMF001',
                'VFLAG' => MenuFlag::BASIC,
                'VICON' => 'brand-appstore',
                'VAPPDESC' => 'Master Application',
                'VURL' => 'HITUAM/bd/master-application',
                'VDESC' => 'Master Application',
                'NSORTAPP' => 101,
                'VTYPEAPP' => 'LRVL-WEB',
                'NSORTPROJECT' => $applicationHituam->IID,
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => null,
            ],
            [
                'VAPPID' => 'HITUAMF002',
                'VFLAG' => MenuFlag::BASIC,
                'VICON' => 'list',
                'VAPPDESC' => 'Master Menu',
                'VURL' => 'HITUAM/bd/master-menu',
                'VDESC' => 'Master Menu',
                'NSORTAPP' => 102,
                'VTYPEAPP' => 'LRVL-WEB',
                'NSORTPROJECT' => $applicationHituam->IID,
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => null,
            ],
            [
                'VAPPID' => 'HITUAMF003',
                'VFLAG' => MenuFlag::BASIC,
                'VICON' => 'shield-half',
                'VAPPDESC' => 'Master Service',
                'VURL' => 'HITUAM/bd/master-service',
                'VDESC' => 'Master Service',
                'NSORTAPP' => 103,
                'VTYPEAPP' => 'LRVL-WEB',
                'NSORTPROJECT' => $applicationHituam->IID,
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => null,
            ],
            [
                'VAPPID' => 'HITUAMF007',
                'VFLAG' => MenuFlag::BASIC,
                'VICON' => 'book',
                'VAPPDESC' => 'Master Role Access',
                'VURL' => 'HITUAM/bd/master-role-access',
                'VDESC' => 'Master Role Access',
                'NSORTAPP' => 106,
                'VTYPEAPP' => 'LRVL-WEB',
                'NSORTPROJECT' => $applicationHituam->IID,
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => null,
            ],
            [
                'VAPPID' => 'HITUAMF008',
                'VFLAG' => MenuFlag::BASIC,
                'VICON' => 'user-shield',
                'VAPPDESC' => 'Master Role Service',
                'VURL' => 'HITUAM/bd/master-role-service',
                'VDESC' => 'Master Role Service',
                'NSORTAPP' => 105,
                'VTYPEAPP' => 'LRVL-WEB',
                'NSORTPROJECT' => $applicationHituam->IID,
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => null,
            ],
            [
                'VAPPID' => 'HITUAMF010',  //FACTWMF001
                'VFLAG' => MenuFlag::BASIC,
                'VICON' => 'settings',
                'VAPPDESC' => 'Configuration',
                'VURL' => 'HITUAM/bd/configuration',
                'VDESC' => 'Configuration',
                'NSORTAPP' => 1,
                'VTYPEAPP' => 'LRVL-WEB',
                'NSORTPROJECT' => $applicationHituam->IID,
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => null,
            ],
            [
                'VAPPID' => 'HITUAMF011',  //FACTWMF004
                'VFLAG' => MenuFlag::BASIC,
                'VICON' => 'news',
                'VAPPDESC' => 'Master News',
                'VURL' => 'HITUAM/bd/master-news',
                'VDESC' => 'Master News',
                'NSORTAPP' => 4,
                'VTYPEAPP' => 'LRVL-WEB',
                'NSORTPROJECT' => $applicationHituam->IID,
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => null,
            ],
        ];

        Menu::truncate();
        Menu::insert($hituamMenus);
    }
}
