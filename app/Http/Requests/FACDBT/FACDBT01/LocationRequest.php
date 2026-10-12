<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
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
        $uniqueLocationNoRule = Rule::unique('INV_TAKING.SIM_INV_LOCATION_TEST', 'location_no');

        $rules = [
            'location_no' => ['required', 'string', 'max:100', $uniqueLocationNoRule],
            'location_group' => ['nullable', 'string', 'max:50'],
            'site' => ['nullable', 'string', 'max:50'],
            'warehouse' => ['nullable', 'string', 'max:100'],
            'location_description' => ['nullable', 'string', 'max:255'],
            'location_group_description' => ['nullable', 'string', 'max:255'],
        ];

        // Jika request adalah Update (PUT/PATCH), abaikan pengecekan unique untuk location_no milik data itu sendiri
        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            // Mengambil instance model Location dari route (parameter {location})
            $locationNo = $this->route('location')->location_no;

            $rules['location_no'] = [
                'required', 
                'string', 
                'max:100', 
                (clone $uniqueLocationNoRule)->ignore($locationNo, 'location_no')
            ];
        }

        return $rules;
    }
}