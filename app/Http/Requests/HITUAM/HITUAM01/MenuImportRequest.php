<?php

namespace App\Http\Requests\HITUAM\HITUAM01;

class MenuImportRequest extends MenuRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ];
    }
}
