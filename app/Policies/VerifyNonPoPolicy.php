<?php

namespace App\Policies;

use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use App\Models\FACTWM02\FACTWM_TRHVERIFY_NON_PO as VerifyNonPo;

class VerifyNonPoPolicy
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

    public function update(User $user, VerifyNonPo $data): bool
    {
        if (!$user->supplierUser()->exists()) {
            return $user->supplierUser()->exists();
        }

        return $user->supplierUser->VSUPPLIER_CODE === $data->VSUPPLIER_CODE;
    }

    public function view(User $user, VerifyNonPo $data): bool
    {
        return $user->supplierUser?->VSUPPLIER_CODE === $data->VSUPPLIER_CODE;
    }

    public function finalPreview(User $user, VerifyNonPo $data): bool
    {
        if (!$user->supplierUser()->exists()) {
            return $user->supplierUser()->exists();
        }

        return $user->supplierUser->VSUPPLIER_CODE === $data->VSUPPLIER_CODE;
    }

    public function previewPdf(User $user, VerifyNonPo $data): bool
    {
        if (!$user->supplierUser()->exists()) {
            return $user->supplierUser()->exists();
        }

        return $user->supplierUser->VSUPPLIER_CODE === $data->VSUPPLIER_CODE;
    }
}
