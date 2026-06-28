<?php

namespace App\Http\Requests\Api;

use Carbon\Carbon;
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
            // Whole-hour alignment is enforced even when clinician_id is null —
            // previously a request with no clinician bypassed the slot-alignment
            // checks in AppointmentService::bookAppointment() (which gate on
            // clinicianId truthiness), letting a patient submit 09:17:00 with
            // no clinician, persisting an off-grid appointment that survives
            // approval. This rule ensures empty-clinician requests still fall
            // on a slot boundary.
            'requested_at' => [
                'required',
                'date',
                'after:now',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $carbon = Carbon::parse($value);
                    if ($carbon->minute !== 0 || $carbon->second !== 0) {
                        $fail('The requested time must be at the top of the hour (e.g., 09:00, 14:00).');
                    }
                },
            ],
            'mode' => ['required', 'in:in_person,online'],
            'reason' => ['nullable', 'string', 'max:500'],
            'clinician_id' => ['nullable', 'exists:clinicians,id'],
        ];
    }
}
