<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use App\Http\Requests\FACDBT\FACDBT01\Concerns\ImportFileRules;

class GroupLocationImportRequest extends GroupLocationRequest
{
    use ImportFileRules;

    public function authorize(): bool
    {
        return true;
    }
}
