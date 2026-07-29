<?php

return [
    'lock_file' => sys_get_temp_dir() . '/welav2-cron.lock',
    'schedule' => [
        'default_enabled' => true,
        'default_interval_minutes' => 5,
        'min_interval_minutes' => 1,
        'max_interval_minutes' => 1440,
    ],
    'steps' => [
        'full_pipeline' => 'run_full_pipeline.php',
        'document_scan' => 'run_document_scan.php',
        'document_upload' => 'run_document_upload.php',
        'image_scan' => 'run_image_scan.php',
        'image_upload' => 'run_image_upload.php',
    ],
];
