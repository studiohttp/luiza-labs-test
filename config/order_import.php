<?php

declare(strict_types=1);

return [
    'batch_size' => (int) env('LEGACY_IMPORT_BATCH_SIZE', 1000),
    'staging_commit_size' => (int) env('LEGACY_IMPORT_STAGING_COMMIT_SIZE', 1000),
    'invalid_detail_limit' => (int) env('LEGACY_IMPORT_INVALID_DETAIL_LIMIT', 100),
    'max_upload_kilobytes' => (int) env('LEGACY_IMPORT_MAX_UPLOAD_KB', 2097152),
];
