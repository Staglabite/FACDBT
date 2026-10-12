<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use Illuminate\Foundation\Http\FormRequest;

class TakingBookRequest extends FormRequest
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
        $rules = [
            // 8 Kolom Composite Primary Key
            'INV_LIST_NO'               => ['required', 'string', 'max:100'],
            'SEQ'                       => ['required', 'integer'],
            'MONTH'                     => ['required', 'integer'],
            'YEAR'                      => ['required', 'integer'],
            'CONTRACT'                  => ['required', 'string', 'max:100'],
            'PART_NO'                   => ['required', 'string', 'max:100'],
            'LOCATION_NO'               => ['required', 'string', 'max:100'],
            'LOT_BATCH_NO'              => ['required', 'string', 'max:100'],

            // Kolom Lainnya
            'QUANTITY'                  => ['nullable', 'numeric'],
            'UNIT_COST'                 => ['nullable', 'numeric'],
            'UNIT_MEAS'                 => ['nullable', 'string', 'max:50'],
            'HANDLING_UNIT_ID'          => ['nullable', 'integer'],
            'HANDLING_UNIT_TYPE_ID'     => ['nullable', 'string', 'max:100'],
            'NPK'                       => ['nullable', 'string', 'max:100'],
            'VERIFIED'                  => ['nullable', 'string', 'max:10'],
            'WDR'                       => ['nullable', 'string', 'max:50'],
            'WAREHOUSE'                 => ['nullable', 'string', 'max:100'],
            'BAY_NO'                    => ['nullable', 'string', 'max:100'],
            'ROW_NO'                    => ['nullable', 'string', 'max:100'],
            'TIER_NO'                   => ['nullable', 'string', 'max:100'],
            'BIN_NO'                    => ['nullable', 'string', 'max:100'],
            'AVAILABILITY_CONTROL_ID'   => ['nullable', 'string', 'max:100'],
            'AVAILABILITY_CONTROL_DESC' => ['nullable', 'string', 'max:255'],
        ];

        // Jika request adalah Update (PUT/PATCH), ubah PK menjadi tidak wajib 
        // karena pada form Edit biasanya PK di-disable / readonly.
        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            $rules['INV_LIST_NO']  = ['nullable', 'string'];
            $rules['SEQ']          = ['nullable', 'integer'];
            $rules['MONTH']        = ['nullable', 'integer'];
            $rules['YEAR']         = ['nullable', 'integer'];
            $rules['CONTRACT']     = ['nullable', 'string'];
            $rules['PART_NO']      = ['nullable', 'string'];
            $rules['LOCATION_NO']  = ['nullable', 'string'];
            $rules['LOT_BATCH_NO'] = ['nullable', 'string'];
        }

        return $rules;
    }
}