<?php

namespace App\Http\Requests\HITUAM\HITUAM01;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurationRequest extends FormRequest
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
            'variable' => [
                'required',
                Rule::unique('hituam.HITUAM_MSHCONFIGURATIONS', 'VVARIABLE')
                    ->whereNull('DDELETE')
            ],
            'value' => 'required',
        ];

        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            $configurationId = $this->route('configuration')->IID;

            $rules['variable'] = ['required', Rule::unique('hituam.HITUAM_MSHCONFIGURATIONS', 'VVARIABLE')->whereNull('DDELETE')->ignore($configurationId, 'IID')];
        }

        return $rules;
    }
}
