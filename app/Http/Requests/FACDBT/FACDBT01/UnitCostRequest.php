<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitCostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Ubah sesuai dengan logic otorisasi Anda jika diperlukan
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Mendefinisikan aturan unique. 
        // Pastikan nama tabel 'SIM_INV_UNITCOST_TEST' sesuai dengan yang ada di database Anda.
        $uniquePartNoRule = Rule::unique('INV_TAKING.SIM_INV_UNITCOST_TEST', 'part_no');

        $rules = [
            'part_no'                    => ['required', 'string', 'max:100', $uniquePartNoRule],
            'inventory_part_description' => ['nullable', 'string', 'max:255'],
            'inventory_uom'              => ['nullable', 'string', 'max:50'],
            'accounting_group'           => ['nullable', 'string', 'max:100'],
            'on_hand_qty'                => ['nullable', 'numeric'],
            'mat_cost'                   => ['nullable', 'numeric'],
            'lab_cost'                   => ['nullable', 'numeric'],
            'foh_cost'                   => ['nullable', 'numeric'],
        ];

        // Jika request adalah Update (PUT/PATCH), abaikan pengecekan unique untuk part_no milik data itu sendiri
        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            // Mengambil instance model Unit Cost dari route (parameter {unitcost})
            // Pastikan parameter di route web.php Anda bernama {unitcost} -> Route::put('/{unitcost}', 'update')
            $partNo = $this->route('unitcost')->part_no;

            $rules['part_no'] = [
                'required', 
                'string', 
                'max:100', 
                (clone $uniquePartNoRule)->ignore($partNo, 'part_no')
            ];
        }

        return $rules;
    }
}