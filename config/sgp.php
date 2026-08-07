<?php

return [
    'release_label' => env('SGP_RELEASE_LABEL', 'Versão 2.0.1 RC2 • Homologação'),

    'storage' => [
        'private_disk' => env('SGP_PRIVATE_DISK', env('FILESYSTEM_DISK', 'local')),
    ],

    'bootstrap' => [
        'administrator_password' => env('SGP_BOOTSTRAP_ADMIN_PASSWORD'),
    ],

    'documents' => [
        'default_timezone' => env('SGP_DOCUMENT_TIMEZONE', 'America/Belem'),
    ],

    'attachments' => [
        'max_kb' => (int) env('SGP_ATTACHMENT_MAX_KB', 10240),
        'allowed_extensions' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'SGP_ATTACHMENT_EXTENSIONS',
                'pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,zip',
            )),
        ))),
    ],
];
