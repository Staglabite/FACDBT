<?php

namespace App\Actions\Invoice;

use App\Contracts\Invoice\InvoiceParsing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OCRParseAction implements InvoiceParsing
{
    public function __construct(private $file) {}

    public function parse(): ?string
    {
        $apiKey = config('ocr.space.api_key');
        $apiUrl = config('ocr.space.api_url');

        try {
            $response = Http::attach(
                'file',
                file_get_contents($this->file->getRealPath()),
                $this->file->getClientOriginalName()
            )->post($apiUrl, [
                'apikey' => $apiKey,
                'language' => 'eng',
                'isOverlayRequired' => 'false',
                'isCreateSearchablePdf' => 'false',
                'isSearchablePdfHideTextLayer' => 'false',
                'scale' => 'true',
                'detectOrientation' => 'false',
                'isTable' => 'false',
                'OCREngine' => 2
            ]);

            return $response->json()['ParsedResults'][0]['ParsedText'];
        } catch (\Exception $e) {
            Log::error('OCR Error', $e);
            throw ValidationException::withMessages([
                'file' => 'Failed to process file'
            ]);
        }
    }
}
