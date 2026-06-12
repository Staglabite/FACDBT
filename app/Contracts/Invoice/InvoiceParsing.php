<?php

namespace App\Contracts\Invoice;

interface InvoiceParsing
{
    // Parsing action
    public function parse(): ?string;
}
