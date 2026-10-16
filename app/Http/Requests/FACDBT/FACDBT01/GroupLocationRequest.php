<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use Illuminate\Foundation\Http\FormRequest;

class GroupLocationRequest extends FormRequest
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
        // Semua kolom NOT NULL dengan panjang maksimal varchar(50) sesuai struktur tabel SIM_INV_GROUP_LOCATION
        return [
            'LOCATION_NO' => ['required', 'string', 'max:50'],
            'ID_LOC' => ['required', 'string', 'max:50'],
            'location_group' => ['required', 'string', 'max:50'],
            'stream' => ['nullable', 'string', 'max:100'],
        ];
    }
}
