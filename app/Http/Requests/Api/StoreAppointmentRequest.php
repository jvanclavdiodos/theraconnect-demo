<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_at' => ['required', 'date', 'after:now'],
            'mode' => ['required', 'in:in_person,online'],
            'reason' => ['nullable', 'string', 'max:500'],
            'clinician_id' => ['nullable', 'exists:clinicians,id'],
        ];
    }
}
