<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use Illuminate\Foundation\Http\FormRequest;

class GroupLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Panjang maksimal sesuai struktur tabel FACDBT_MSHGROUPLOCATIONS
        return [
            'VLOCATIONNO' => ['required', 'string', 'max:50'],
            'VIDLOC' => ['required', 'string', 'max:50'],
            'VLOCGROUP' => ['required', 'string', 'max:50'],
            'VSTREAM' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'VLOCATIONNO' => 'Location No',
            'VIDLOC' => 'ID Loc',
            'VLOCGROUP' => 'Location Group',
            'VSTREAM' => 'Stream',
        ];
    }
}
