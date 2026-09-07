<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document uploads
    |--------------------------------------------------------------------------
    */

    'documents' => [
        // Storage disk for uploaded originals.
        'disk' => env('RAAS_DOCUMENTS_DISK', env('FILESYSTEM_DISK', 's3')),

        // Max size per file, in kilobytes (validation `max` rule).
        'max_size_kb' => (int) env('RAAS_DOCUMENTS_MAX_SIZE_KB', 51200), // 50 MB

        // Accepted extensions (the `mimes` rule inspects file contents).
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'txt', 'md', 'markdown', 'html', 'csv', 'pptx'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Organizations
    |--------------------------------------------------------------------------
    */

    'organizations' => [
        // Default max documents an organization may retain across all its
        // projects, used when the organization has no explicit `document_limit`.
        // Null means unlimited.
        'default_document_limit' => match (($value = env('RAAS_ORG_DEFAULT_DOCUMENT_LIMIT', 1000))) {
            null, '', 'null' => null,
            default => (int) $value,
        },
    ],

];
