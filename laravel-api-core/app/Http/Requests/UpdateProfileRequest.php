<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Email sengaja tidak bisa diubah (perubahan email butuh verifikasi OTP ulang).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'     => 'required_without:timezone|string|max:255',
            'timezone' => 'required_without:name|timezone',
        ];
    }
}
