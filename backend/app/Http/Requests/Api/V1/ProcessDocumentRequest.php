<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessDocumentRequest extends FormRequest
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
            'strategy' => [
                'nullable',
                'string',
                Rule::in(['auto', 'recursive', 'fixed', 'sentence', 'markdown', 'semantic']),
            ],
            'strategy_config' => ['nullable', 'array'],
        ];
    }
}
