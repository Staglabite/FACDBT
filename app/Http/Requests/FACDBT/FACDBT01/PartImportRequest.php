<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

class PartImportRequest extends PartRequest
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
