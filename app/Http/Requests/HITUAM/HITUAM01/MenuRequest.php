<?php

namespace App\Http\Requests\HITUAM\HITUAM01;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
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
        $uniqueAppIdRule = Rule::unique('hituam.HITUAM_MSHMENUS', 'VAPPID')
            ->whereNull('DDELETE');

        $rules = [
            'app_id' => ['required', 'string', 'max:50', $uniqueAppIdRule],
            'name' => ['required', 'string', 'max:100'],
            'flag' => ['required', 'string', 'max:50'],
            'icon' => ['required', 'string', 'max:100'],
            'url' => ['required'],
            'description' => ['required'],
            'type' => ['required'],
            'env_app' => ['nullable', 'string', 'max:100'],
            'order' => ['required'],
            'application' => ['required'],
            'parent' => ['nullable'],
        ];

        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            $menuId = $this->route('menu')->IID;

            $rules['app_id'] = ['required', 'string', 'max:50', (clone $uniqueAppIdRule)->ignore($menuId, 'IID')];
        }

        return $rules;
    }
}
