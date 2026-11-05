<?php

namespace App\Policies;

use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use App\Models\FACTWM02\FACTWM_TRHVERIFY_PO as VerifyPo;

class VerifyPoPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function create(User $user): bool
    {
        return $user->supplierUser()->exists();
    }

    public function finalPreview(User $user, VerifyPo $data): bool
    {
        if (!$user->supplierUser()->exists()) {
            return $user->supplierUser()->exists();
        }

        return $user->supplierUser->VSUPPLIER_CODE === $data->VSUPPLIER_CODE;
    }

    public function previewPdf(User $user, VerifyPo $data): bool
    {
        if (!$user->supplierUser()->exists()) {
            return $user->supplierUser()->exists();
        }

        return $user->supplierUser->VSUPPLIER_CODE === $data->VSUPPLIER_CODE;
    }
}
