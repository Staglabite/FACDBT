<?php

namespace App\Services\HITUAM;

use App\Models\FACTWM01\FACTWM_MSHSUPPLIER_COMMUNICATION_METHOD as SupplierUser;
use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function syncSupplierUser(User $user, array $data)
    {
        $supplierUser = SupplierUser::specific($data)->first();

        if (! $supplierUser) {
            return;
        }

        $supplierUser->update([
            'VUSERNAME' => $user->VUSERNAME,
            'IUSER_ID' => $user->IID
        ]);
    }

    public function generatePassword($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
        return substr(str_shuffle(str_repeat($characters, 5)), 0, $length);
    }
}
