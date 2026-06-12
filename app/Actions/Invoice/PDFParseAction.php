<?php

namespace App\Actions\Invoice;

use App\Contracts\Invoice\InvoiceParsing;
use Smalot\PdfParser\Parser;

class PDFParseAction implements InvoiceParsing
{
    public function __construct(private $file) {}

    public function parse(): ?string {
        $parser = new Parser();
        $file = $parser->parseFile($this->file);

        return $file->getText();
    }
}
