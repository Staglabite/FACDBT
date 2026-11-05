<?php

namespace Database\Seeders;

use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use App\Models\HITUAM01\HITUAM_MSHUSERROLE as UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

use function Symfony\Component\Clock\now;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'VEMPNO' => '111111',
                'VUSERNAME' => 'admin',
                'VEMAIL' => 'admin@admin.com',
                'VPASSWORD' => Hash::make('password'),
                'VCREA' => 'Seeder',
                'DCREA' => now(),
                'DMODI' => now(),
            ],
        ];

        User::truncate();
        User::insert($users);

        // user role
        UserRole::insert([
            'VUSERNAME' => 'admin',
            'VROLE' => 'Admin',
        ]);
    }
}
