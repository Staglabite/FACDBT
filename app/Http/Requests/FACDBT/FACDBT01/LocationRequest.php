<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use App\Services\FACDBT\FacdbtTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Location No unik di antara data yang belum dihapus; Location No milik data yang sudah dihapus
     * boleh dipakai lagi (datanya dipulihkan, lihat FACDBTF001::store()).
     */
    public function rules(): array
    {
        $unique = Rule::unique('FACDBT.' . FacdbtTable::LOCATIONS, 'VLOCATIONNO')->whereNull(FacdbtTable::DELETED_AT);
        if ($location = $this->route('location')) {
            $unique->ignore($location->IID, 'IID');
        }

        return [
            'VLOCATIONNO' => ['required', 'string', 'max:100', $unique],
            'VLOCGROUP' => ['nullable', 'string', 'max:50'],
            'VSITE' => ['nullable', 'string', 'max:50'],
            'VWAREHOUSE' => ['nullable', 'string', 'max:100'],
            'VLOCDESC' => ['nullable', 'string', 'max:255'],
            'VLOCGROUPDESC' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'VLOCATIONNO' => 'Location No',
            'VLOCGROUP' => 'Location Group',
            'VSITE' => 'Site',
            'VWAREHOUSE' => 'Warehouse',
            'VLOCDESC' => 'Location Description',
            'VLOCGROUPDESC' => 'Location Group Description',
        ];
    }
}
