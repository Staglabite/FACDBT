<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use App\Services\FACDBT\FacdbtTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Part No unik di antara data yang belum dihapus; Part No milik data yang sudah dihapus boleh dipakai
     * lagi (datanya dipulihkan, lihat FACDBTF003::store()).
     */
    public function rules(): array
    {
        $unique = Rule::unique('INV_TAKING.' . FacdbtTable::UNIT_COSTS, 'VPARTNO')->whereNull(FacdbtTable::DELETED_AT);
        if ($unitCost = $this->route('unitcost')) {
            $unique->ignore($unitCost->IID, 'IID');
        }

        return [
            'VPARTNO'    => ['required', 'string', 'max:100', $unique],
            'VPARTDESC'  => ['nullable', 'string', 'max:255'],
            'VUOM'       => ['nullable', 'string', 'max:50'],
            'VACCGROUP'  => ['nullable', 'string', 'max:100'],
            'NONHANDQTY' => ['nullable', 'numeric'],
            'MMATCOST'   => ['nullable', 'numeric'],
            'MLABCOST'   => ['nullable', 'numeric'],
            'MFOHCOST'   => ['nullable', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return [
            'VPARTNO' => 'Part No',
            'VPARTDESC' => 'Description',
            'VUOM' => 'UOM',
            'VACCGROUP' => 'Accounting Group',
            'NONHANDQTY' => 'On Hand Qty',
            'MMATCOST' => 'Material Cost',
            'MLABCOST' => 'Labor Cost',
            'MFOHCOST' => 'FOH Cost',
        ];
    }
}
