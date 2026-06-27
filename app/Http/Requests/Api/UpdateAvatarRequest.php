<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates avatar uploads across the three surfaces that accept them
 * (admin/clinician web, patient portal, patient mobile API). Centralizes
 * the rules previously duplicated as inline `$request->validate()` calls in
 * AccountController, PortalProfileController, and ProfileController.
 *
 * `dimensions` caps the pixel size; `max` caps the byte size; the `image`
 * rule verifies PHP can read it as a real image (not just an extension check).
 */
class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
                'dimensions:max_width=1024,max_height=1024',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.max' => 'The avatar must not be greater than 4 MB.',
            'avatar.mimes' => 'The avatar must be a JPG, JPEG, PNG, or WebP image.',
            'avatar.dimensions' => 'The avatar must be 1024x1024 pixels or smaller.',
        ];
    }
}
