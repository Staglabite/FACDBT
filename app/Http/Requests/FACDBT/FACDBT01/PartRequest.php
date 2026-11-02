<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use App\Services\FACDBT\FacdbtTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Part No unik di antara data yang belum dihapus; Part No milik data yang sudah dihapus boleh dipakai
     * lagi (datanya dipulihkan, lihat FACDBTF002::store()).
     */
    public function rules(): array
    {
        $unique = Rule::unique('FACDBT.' . FacdbtTable::PARTS, 'VPARTNO')->whereNull(FacdbtTable::DELETED_AT);
        if ($part = $this->route('part')) {
            $unique->ignore($part->IID, 'IID');
        }

        return [
            'VPARTNO'    => ['required', 'string', 'max:100', $unique],
            'VPARTDESC'  => ['nullable', 'string', 'max:255'],
            'VACCGROUP'  => ['nullable', 'string', 'max:100'],
            'VSEG'       => ['nullable', 'string', 'max:100'],
            'VPRODGROUP' => ['nullable', 'string', 'max:100'],
            'VPARTGROUP' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'VPARTNO' => 'Part No',
            'VPARTDESC' => 'Description',
            'VACCGROUP' => 'Accounting Group',
            'VSEG' => 'Seg',
            'VPRODGROUP' => 'Prod Group',
            'VPARTGROUP' => 'Part Group',
        ];
    }
}
