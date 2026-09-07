<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'welcome_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'suggested_questions' => ['sometimes', 'nullable', 'array', 'max:6'],
            'suggested_questions.*' => ['string', 'max:200'],
            'daily_query_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
