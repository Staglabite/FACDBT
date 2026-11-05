<?php

namespace App\Http\Requests\HITUAM\HITUAM01;

use Illuminate\Foundation\Http\FormRequest;

class NewsRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH') || ($this->isMethod('POST') && $this->route('news'));

        return [
            'title' => [$isUpdate ? 'nullable' : 'required', 'string', 'max:255'],
            // 'publish_to_vendor' => [$isUpdate ? 'nullable' : 'required', 'array'],
            // 'publish_to_vendor.*' => ['string'],
            'upload_file' => ['nullable', 'file', 'max:5120'],
            'upload_foto' => ['nullable', 'image', 'max:2048'],
            'content' => [$isUpdate ? 'nullable' : 'required', 'string'],
            'publish' => ['nullable'],
            'delete_file' => ['nullable', 'string'],
            'delete_foto' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the validation error messages.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Title is required',
            'title.string' => 'Title must be a string',
            'title.max' => 'Title may not be greater than 255 characters',
            // 'publish_to_vendor.required' => 'Publish to supplier is required',
            // 'publish_to_vendor.array' => 'Publish to supplier must be an array',
            // 'publish_to_vendor.*.string' => 'Publish to supplier values must be strings',
            'upload_file.file' => 'Upload file must be a valid file',
            'upload_file.max' => 'Upload file may not be greater than 5MB',
            'upload_foto.image' => 'Upload foto must be an image',
            'upload_foto.max' => 'Upload foto may not be greater than 2MB',
            'content.required' => 'Content is required',
            'content.string' => 'Content must be a string',
        ];
    }
}
