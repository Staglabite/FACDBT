<?php

return [
    // 'method' => env('OCR_METHOD', 'kbi'), // smalot | kbi | kbi_http | space
    'method' => env('OCR_METHOD', 'kbi'), // smalot | kbi | kbi_http | space

    'kbi' => [
        /**
         * Service Location is where ocr service cli.py location
         */
        'service_loc' => env('OCR_KBI_LOCATION', '~/Projects/ocr-service'),
        'venv' => env('OCR_KBI_VENV', false),
        'os' => env('OCR_SYSTEM_OPERATION', 'linux'),
        'venv_name' => env('OCR_VENV_NAME', 'venv'),
        'ghostscript_binary' => env('OCR_GHOSTSCRIPT_BINARY', ''),
        'ghostscript_required' => env('OCR_GHOSTSCRIPT_REQUIRED', false),
    ],

    'kbi_http' => [
        'url' => env('OCR_KBI_HTTP_URL', ''),
        'token' => env('OCR_KBI_HTTP_TOKEN', ''),
        'timeout' => env('OCR_KBI_HTTP_TIMEOUT', 300),
        'verify_ssl' => env('OCR_KBI_HTTP_VERIFY_SSL', true),
        'ghostscript_binary' => env('OCR_KBI_HTTP_GHOSTSCRIPT_BINARY', env('OCR_GHOSTSCRIPT_BINARY', '')),
        'ghostscript_required' => env('OCR_KBI_HTTP_GHOSTSCRIPT_REQUIRED', env('OCR_GHOSTSCRIPT_REQUIRED', false)),
    ],

    /**
     * USING 3rd party OCR SPACE
     *
     * see: https://ocr.space/OCRAPI
     */
    'space' => [
        /**
         * OCR Space endpoint
         */
        'api_url' => env('OCR_SPACE_URL', 'https://api.ocr.space/parse/image'),


        /**
         * Your OCR Space Key
         */
        'api_key' => env('OCR_SPACE_KEY', null),
    ]
];
