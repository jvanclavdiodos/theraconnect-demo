<?php

namespace App\Http\Requests\Api;

use App\Rules\AssignmentSubmissionFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240', new AssignmentSubmissionFile],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The file must not be greater than 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasFile('file') || $this->filled('content')) {
                return;
            }

            $message = 'Write a response or attach a file before submitting.';
            $validator->errors()->add('content', $message);
            $validator->errors()->add('file', $message);
        });
    }
}
