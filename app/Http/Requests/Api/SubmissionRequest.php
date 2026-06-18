<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

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
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,txt,rtf,jpg,jpeg,png'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The file must not be greater than 10 MB.',
            'file.mimes' => 'The file must be a PDF, DOC, DOCX, TXT, RTF, JPG, or PNG.',
        ];
    }

    protected function passedValidation(): void
    {
        if (! $this->hasFile('file') && ! $this->filled('content')) {
            abort(response()->json([
                'message' => 'At least one of content or file must be provided.',
                'errors' => [
                    'content' => ['Either content or a file is required.'],
                    'file' => ['Either content or a file is required.'],
                ],
            ], 422));
        }
    }
}
