<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates worksheet assignment creation on the staff web dashboard.
 * Replaces the inline `$request->validate()` in WebAssignmentController@store
 * so the rule set is testable and the controller stays thin. The clinician_id
 * field is conditionally required for admins (who must attribute the
 * assignment to a clinician); clinicians authoring their own assignments
 * are auto-attributed based on the authed user, so the field is hidden
 * from the form on the clinician surface.
 */
class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'patient_id' => ['required', 'exists:patients,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png',
            ],
        ];

        // Admins must attribute the assignment to a clinician; clinicians
        // authoring their own assignments do not send clinician_id.
        if (! auth()->user()?->clinician) {
            $rules['clinician_id'] = ['required', 'exists:clinicians,id'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'attachment.max' => 'The attachment must not be greater than 10 MB.',
            'attachment.mimes' => 'The attachment must be a PDF, Office document, text, or image.',
        ];
    }
}
