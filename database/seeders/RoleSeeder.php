<?php

namespace Database\Seeders;

use App\Models\HITUAM01\HITUAM_MSHROLE;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = [
            [
                'VROLENAME' => 'Admin',
                'VROLEDESC' => 'Desc',
                'BSTATUS' => true
            ]
        ];

        HITUAM_MSHROLE::truncate();
        HITUAM_MSHROLE::insert($role);
    }
}
