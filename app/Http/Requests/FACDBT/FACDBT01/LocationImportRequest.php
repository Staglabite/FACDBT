<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use App\Http\Requests\FACDBT\FACDBT01\Concerns\ImportFileRules;

class LocationImportRequest extends LocationRequest
{
    use ImportFileRules;

    public function authorize(): bool
    {
        return true;
    }
}
