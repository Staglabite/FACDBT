<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use Illuminate\Foundation\Http\FormRequest;

class TakingEventPartImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ];
    }
}
