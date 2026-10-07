<?php

namespace App\Http\Requests;

use App\Enums\ActivityAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProjectActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
            'action'   => ['nullable', Rule::enum(ActivityAction::class)],
            'actor_id' => 'nullable|integer|min:1',
            'task_id'  => 'nullable|integer|min:1',
        ];
    }
}
