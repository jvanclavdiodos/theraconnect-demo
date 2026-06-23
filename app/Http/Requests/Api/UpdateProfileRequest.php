<?php

namespace App\Http\Requests\Api;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', Rule::in(Patient::GENDERS)],
            'educational_attainment' => ['nullable', 'string', Rule::in(Patient::EDUCATION_LEVELS)],
            'employment_status' => ['nullable', 'string', Rule::in(Patient::EMPLOYMENT_STATUSES)],
            'personal_issues' => ['nullable', 'string', 'max:2000'],
            'contact_no' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
        ];
    }
}
