<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use App\Models\HITUAM01\HITUAM_MSHROLE_ACCESS as RoleAccess;

class RoleAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menus = Menu::all();
        $accesses = [];

        foreach ($menus as $menu) {
            $accesses[] = [
                'VROLE' => 'Admin',
                'VMENUID' => $menu->VAPPID,
            ];
        }

        RoleAccess::truncate();
        RoleAccess::insert($accesses);
    }
}
