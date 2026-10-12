<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

class UnitCostImportRequest extends UnitCostRequest
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
