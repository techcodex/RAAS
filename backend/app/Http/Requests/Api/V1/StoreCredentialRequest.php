<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCredentialRequest extends FormRequest
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
            'api_key' => ['required', 'string', 'min:10', 'max:500'],
            'model' => [
                'nullable', 'string',
                Rule::in(['claude-opus-5', 'claude-sonnet-5', 'claude-haiku-4-5']),
            ],
        ];
    }
}
