<?php

namespace App\Http\Requests\FACDBT\FACDBT01\Concerns;

/**
 * Validasi file import master (CSV / Excel).
 * - extensions : nama file harus .xlsx / .xls / .csv
 * - mimes      : isi file dicek dari kontennya; CSV sederhana sering terbaca sebagai teks biasa (txt) dan
 *                file .xlsx dari sebagian aplikasi terbaca sebagai zip, jadi keduanya ikut diterima
 *                (nama file tetap dibatasi oleh aturan extensions).
 */
trait ImportFileRules
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'mimes:xlsx,xls,csv,txt,zip'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.extensions' => 'File must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.mimes' => 'File must be a CSV or Excel file (.csv, .xlsx, .xls).',
        ];
    }
}
