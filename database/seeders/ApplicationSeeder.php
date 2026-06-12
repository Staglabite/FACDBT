<?php

namespace Database\Seeders;

use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applications = [
            [
                'VPROJECTDESC' => 'User Access Management',
                'VDEPT' => 'HITUAM',
                'VDATABASE' => 'HITUAM',
                'BIS_EMBED' => true,
                'NORDERPROJECT' => 0,
                'VICON' => 'user-cog',
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => now(),
            ],
            [
                'VPROJECTDESC' => '3 Way Matching',
                'VDEPT' => 'FACTWM',
                'VDATABASE' => 'FACTWM',
                'BIS_EMBED' => true,
                'NORDERPROJECT' => 0,
                'VICON' => 'invoice',
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => now(),
            ],
        ];

        Application::truncate();
        Application::insert($applications);
    }
}
