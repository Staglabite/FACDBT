<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use Illuminate\Foundation\Http\FormRequest;

class TakingBookImportRequest extends FormRequest
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
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240' // Maksimal ukuran file 10MB
            ],
        ];
    }
    
    /**
     * Custom error messages (Optional)
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please upload an Excel file.',
            'file.mimes'    => 'The file must be a file of type: xlsx, xls, csv.',
            'file.max'      => 'The file size cannot exceed 10MB.',
        ];
    }
}