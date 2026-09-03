<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
        $extensions = implode(',', config('raas.documents.allowed_extensions'));
        $maxKb = config('raas.documents.max_size_kb');

        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', "mimes:{$extensions}", "max:{$maxKb}"],
        ];
    }

    /**
     * Accept a single `file` field as a convenience and normalize it to `files[]`.
     */
    protected function prepareForValidation(): void
    {
        if ($this->hasFile('file') && ! $this->hasFile('files')) {
            $this->files->set('files', [$this->file('file')]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $project = $this->route('project');
                $quota = (int) config('raas.documents.per_project_quota');
                $incoming = count($this->file('files', []));

                if ($project->documents()->count() + $incoming > $quota) {
                    $validator->errors()->add(
                        'files',
                        "This project's document limit ({$quota}) would be exceeded.",
                    );
                }
            },
        ];
    }
}
