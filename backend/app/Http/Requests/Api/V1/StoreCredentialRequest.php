<?php

namespace App\Http\Requests\Api\V1;

use App\Support\LlmProviders;
use Illuminate\Contracts\Validation\Validator;
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
            'provider' => ['required', 'string', Rule::in(LlmProviders::providers())],
            'api_key' => ['required', 'string', 'min:10', 'max:500'],
            'model' => ['nullable', 'string', Rule::in(LlmProviders::allModels())],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $model = $this->input('model');
                $provider = $this->input('provider');

                if ($model && $provider && ! LlmProviders::supportsModel($provider, $model)) {
                    $validator->errors()->add('model', "'{$model}' is not a {$provider} model.");
                }
            },
        ];
    }
}
