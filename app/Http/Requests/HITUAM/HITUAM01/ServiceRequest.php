<?php

namespace App\Http\Requests\HITUAM\HITUAM01;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
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
        $uniqueServiceNameRule = Rule::unique('hituam.HITUAM_MSHSERVICES', 'VNAME')
            ->whereNull('DDELETE');

        $rules = [
            'name' => ['required', 'string', 'max:50', $uniqueServiceNameRule],
            'description' => ['required', 'string', 'max:50'],
            'method' => ['required', 'string', 'max:100'],
            'application' => ['required'],
            'menu' => ['required'],
            'url' => ['required'],
            'begin' => ['required', 'date'],
            'end' => ['required', 'date'],
        ];

        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            $serviceId = $this->route('service')->IID;

            $rules['name'] = ['required', 'string', 'max:50', (clone $uniqueServiceNameRule)->ignore($serviceId, 'IID')];
        }

        return $rules;
    }
}
