<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class AssignmentSubmissionFile implements ValidationRule
{
    private const MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
        ],
        'txt' => ['text/plain'],
        'rtf' => ['application/rtf', 'text/rtf', 'text/plain'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The uploaded file could not be read. Please choose it again.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $mimeType = $value->getMimeType();

        if (! isset(self::MIME_TYPES[$extension]) || ! in_array($mimeType, self::MIME_TYPES[$extension], true)) {
            $fail('The file must be a PDF, DOC, DOCX, TXT, RTF, JPG, or PNG.');

            return;
        }

        if ($extension === 'docx' && in_array($mimeType, ['application/zip', 'application/x-zip-compressed'], true)
            && ! $this->isWordDocument($value->getRealPath())) {
            $fail('The selected DOCX file is not a valid Word document.');
        }
    }

    private function isWordDocument(string|false $path): bool
    {
        if ($path === false) {
            return false;
        }

        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            return false;
        }

        $valid = $archive->locateName('[Content_Types].xml') !== false
            && $archive->locateName('word/document.xml') !== false;
        $archive->close();

        return $valid;
    }
}
