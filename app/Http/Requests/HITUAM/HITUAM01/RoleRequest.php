<?php

namespace App\Http\Requests\HITUAM\HITUAM01;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
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
        $uniqueRoleNameRule = Rule::unique('hituam.HITUAM_MSHROLES', 'VROLENAME')
            ->whereNull('DDELETE');

        $rules = [
            'name' => ['required', 'string', 'max:50', $uniqueRoleNameRule],
            'description' => ['required', 'string', 'max:100'],
            'status' => ['required', 'boolean'],
            'menus' => ['nullable', 'array'],
            'services' => ['nullable', 'array'],
            'unmenus' => ['nullable', 'array'],
            'unservices' => ['nullable', 'array'],
        ];

        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            $roleId = $this->route('role')->NID;

            $rules['name'] = ['required', 'string', 'max:50', (clone $uniqueRoleNameRule)->ignore($roleId, 'NID')];
        }

        return $rules;
    }
}
