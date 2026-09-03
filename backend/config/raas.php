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

        // Max documents retained per project.
        'per_project_quota' => (int) env('RAAS_DOCUMENTS_PER_PROJECT_QUOTA', 500),
    ],

];
