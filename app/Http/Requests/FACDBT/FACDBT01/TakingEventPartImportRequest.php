<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use App\Http\Requests\FACDBT\FACDBT01\Concerns\ImportFileRules;
use Illuminate\Foundation\Http\FormRequest;

class TakingEventPartImportRequest extends FormRequest
{
    use ImportFileRules;

    public function authorize(): bool
    {
        return true;
    }
}
