<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class DailySummaryRequest extends FormRequest
{
    private const MAX_RANGE_DAYS = 31;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => 'nullable|date_format:Y-m-d|required_with:to',
            'to'   => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || !$this->filled(['from', 'to'])) {
                    return;
                }

                $days = Carbon::createFromFormat('!Y-m-d', $this->input('from'))
                    ->diffInDays(Carbon::createFromFormat('!Y-m-d', $this->input('to')));

                if ($days >= self::MAX_RANGE_DAYS) {
                    $validator->errors()->add('to', 'The date range may not exceed ' . self::MAX_RANGE_DAYS . ' days.');
                }
            },
        ];
    }
}
