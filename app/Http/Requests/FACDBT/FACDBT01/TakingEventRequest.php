<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

use App\Services\FACDBT\FacdbtTable;
use App\Services\FACDBT\TakingEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TakingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['VEVENTTYPE' => strtoupper(trim((string) $this->VEVENTTYPE))]);
    }

    /**
     * Satu periode (DTAKINGDATE) hanya punya satu event aktif; periode yang event-nya pernah dihapus boleh
     * diberi event lagi (baris lama dipulihkan, lihat FACDBTF006::store()).
     */
    public function rules(): array
    {
        $id = $this->route('event');

        return [
            'DTAKINGDATE' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('FACDBT.' . TakingEvent::TABLE, 'DTAKINGDATE')
                    ->whereNull(FacdbtTable::DELETED_AT)
                    ->ignore($id, 'IID'),
            ],
            'VEVENTTYPE' => ['required', Rule::in(array_keys(TakingEvent::TYPES))],
            'VDESC' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'DTAKINGDATE.unique' => 'This taking period already has an event.',
        ];
    }
}
