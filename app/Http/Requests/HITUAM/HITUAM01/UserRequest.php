<?php

namespace App\Http\Requests\HITUAM\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHUSER;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('role[]')) {
            $this->merge([
                'role' => $this->input('role[]'),
            ]);
        }

        $this->merge([
            'role' => $this->input('role', []),
        ]);
    }

    public function rules(): array
    {
        $userType = $this->input('user_type');
        $isUpdate = in_array($this->method(), ['PUT', 'PATCH']);

        return [
            'user_type' => ['required'],

            'username' => [
                'required',
                'string',
                'max:50',
            ],

            'email' => [
                'required',
                'email',
                'max:50',
            ],

            'npk' => [
                $userType === 'internal' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'password' => [
                $isUpdate ? 'nullable' : 'required',
                'string',
                'min:6',
            ],

            'role'   => ['nullable', 'array'],
            'role.*' => ['integer'],

            'supplier' => [
                Rule::requiredIf($userType === 'external'),
            ],

            'user_supplier' => [
                Rule::requiredIf($userType === 'external'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'role.array' => 'Role format is invalid.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $userId = optional($this->route('user'))->IID;

            $this->checkField($validator, 'VUSERNAME', 'username');
            $this->checkField($validator, 'VEMAIL', 'email');
            $this->checkField($validator, 'VEMPNO', 'npk');
        });
    }

    private function checkField($validator, $column, $inputName)
    {
        $value = $this->input($inputName);
        $userId = optional($this->route('user'))->IID;

        if (!$value) {
            return;
        }

        $user = HITUAM_MSHUSER::withTrashed()
            ->where($column, $value)
            ->when($userId, fn($q) => $q->where('IID', '!=', $userId))
            ->first();

        if (!$user) {
            return;
        }

        $isDeleted = $user->DDELETE !== null;

        $fieldName = match ($inputName) {
            'username' => 'username',
            'email' => 'email',
            'npk' => 'NPK',
            default => $inputName
        };

        if ($isDeleted) {

            $validator->errors()->add(
                $inputName,
                "This {$fieldName} already exists but the account has been deleted."
            );
        } else {

            $validator->errors()->add(
                $inputName,
                "This {$fieldName} is already in use."
            );
        }
    }
}
