<?php

namespace App\Http\Requests\FACDBT\FACDBT01; // Sesuaikan menjadi FACDBT01 jika Anda meletakkannya di folder tersebut

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Mendefinisikan aturan unique. 
        // Menggunakan prefix koneksi 'INV_TAKING.' agar Laravel mencari di database yang tepat.
        $uniquePartNoRule = Rule::unique('INV_TAKING.SIM_INV_PART_TEST', 'part_no');

        $rules = [
            'part_no'                    => ['required', 'string', 'max:100', $uniquePartNoRule],
            'inventory_part_description' => ['nullable', 'string', 'max:255'],
            'accounting_group'           => ['nullable', 'string', 'max:100'],
            'stream'                     => ['nullable', 'string', 'max:100'],
            'seg'                        => ['nullable', 'string', 'max:100'],
            'prod_group'                 => ['nullable', 'string', 'max:100'],
            'part_group'                 => ['nullable', 'string', 'max:100'],
        ];

        // Jika request adalah Update (PUT/PATCH), abaikan pengecekan unique untuk part_no milik data itu sendiri
        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            // Mengambil instance model Part dari route (parameter {part})
            // Pastikan parameter di route web.php Anda bernama {part} -> Route::put('/{part}', 'update')
            $partNo = $this->route('part')->part_no;

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