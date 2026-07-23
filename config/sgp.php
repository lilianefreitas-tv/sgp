<?php

return [
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
