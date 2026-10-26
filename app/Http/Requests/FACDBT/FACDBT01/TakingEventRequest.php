<?php

namespace App\Http\Requests\FACDBT\FACDBT01;

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
        $this->merge(['EVENT_TYPE' => strtoupper(trim((string) $this->EVENT_TYPE))]);
    }

    /**
     * Satu periode (TAKING_DATE) hanya punya satu event.
     */
    public function rules(): array
    {
        $id = $this->route('event');

        return [
            'TAKING_DATE' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('INV_TAKING.' . TakingEvent::TABLE, 'TAKING_DATE')->ignore($id, 'IID'),
            ],
            'EVENT_TYPE' => ['required', Rule::in(array_keys(TakingEvent::TYPES))],
            'DESCRIPTION' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'TAKING_DATE.unique' => 'This taking period already has an event.',
        ];
    }
}
