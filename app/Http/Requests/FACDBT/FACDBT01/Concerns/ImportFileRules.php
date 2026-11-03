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
            // File ditolak PHP sebelum sampai ke aplikasi, biasanya karena melebihi upload_max_filesize / post_max_size
            // php.ini (bawaan PHP 2M / 8M); batas ini hanya bisa dinaikkan di php.ini, tidak dari aplikasi
            'file.uploaded' => sprintf(
                'File failed to upload. The file is probably larger than the server upload limit (upload_max_filesize = %s, post_max_size = %s). Ask the administrator to raise these limits in php.ini, or split the file.',
                ini_get('upload_max_filesize') ?: '-',
                ini_get('post_max_size') ?: '-'
            ),
            'file.extensions' => 'File must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.mimes' => 'File must be a CSV or Excel file (.csv, .xlsx, .xls).',
        ];
    }
}
