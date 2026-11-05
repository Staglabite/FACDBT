<?php

namespace App\Actions\Invoice;

class ExtractText
{
    public static function extract($file, ?int $renderApi = null)
    {
        $method = config('ocr.method');

        if ($method === 'kbi') {
            return (new OCRKelolaAction($file, $renderApi))->parse();
        }

        if ($method === 'kbi_http') {
            return (new OCRHttpAction($file, $renderApi))->parse();
        }

        $extension = strtolower($file->getClientOriginalExtension());

        // Try PDF parsing first for PDF files, fallback to OCR SPACE
        if ($extension === 'pdf') {
            $pdfParse = new PDFParseAction($file);
            $text = $pdfParse->parse();
            if (!empty(trim($text))) {
                return $text;
            }
        }

        // Use OCR for non-PDF files or when PDF parsing fails
        return (new OCRParseAction($file))->parse();
    }
}
