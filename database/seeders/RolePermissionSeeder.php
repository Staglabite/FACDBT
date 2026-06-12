<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\HITUAM01\HITUAM_MSHSERVICE as Service;
use App\Models\HITUAM01\HITUAM_MSHROLE_SERVICE as RolePermission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::all();

        $rolePermission = [];

        foreach ($services as $service) {
            $rolePermission[] = [
                'VROLE' => 'Admin',
                'VSERVICE' => $service->VNAME,
                "DBEGINEFF" => $service->DBEGINEFF,
                "DENDEFF" => $service->DENDEFF,
            ];
        }

        RolePermission::truncate();
        RolePermission::insert($rolePermission);
    }
}
