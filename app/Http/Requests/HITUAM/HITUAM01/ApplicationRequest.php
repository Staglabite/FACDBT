<?php

namespace App\Http\Requests\HITUAM\HITUAM01;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('host') && $this->filled('url')) {
            $this->merge([
                'host' => $this->input('url'),
            ]);
        }
    }

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
        $uniqueCodeRule = Rule::unique('hituam.HITUAM_MSHAPPLICATION', 'VDEPT')
            ->whereNull('DDELETE');

        $rules = [
            'code' => ['required', 'string', 'max:50', $uniqueCodeRule],
            'desc' => ['required', 'string', 'max:100'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'pic' => ['nullable', 'string', 'max:100'],
            'portal' => ['nullable', 'string', 'max:100'],
            'portal_access' => ['nullable', 'string', 'max:100'],
            'operational' => ['nullable', 'string', 'max:255'],
            'std' => ['nullable', 'string', 'max:100'],
            'protal_access' => ['nullable', 'string', 'max:100'],
            'host' => ['nullable', 'string'],
            'publish' => ['nullable', 'string'],
            'database' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
            'icon' => ['required', 'string'],
            'is_embedded' => ['nullable', 'string', 'in:true,false'],
        ];

        if ($this->method() === 'PUT' || $this->method() === 'PATCH') {
            $applicationId = $this->route('application')->IID;

            $rules['code'] = ['required', 'string', 'max:50', (clone $uniqueCodeRule)->ignore($applicationId, 'IID')];
        }

        return $rules;
    }
}
