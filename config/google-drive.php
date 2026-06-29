<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Drive Backup Settings
    |--------------------------------------------------------------------------
    |
    | These settings are used for automated database backups to Google Drive.
    | The service account JSON key file should be placed in storage/app/google-drive/.
    |
    */
    'service_account_credentials' => storage_path('app/google-drive/service-account.json'),

    // Google Drive folder ID where backups will be stored (optional — if null, creates in root)
    'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', null),

    // Number of backup files to keep on Google Drive (oldest are deleted first)
    'max_backups' => (int) env('GOOGLE_DRIVE_MAX_BACKUPS', 7),

    // Enable automatic scheduled backups
    'scheduled_backup' => (bool) env('GOOGLE_DRIVE_SCHEDULED_BACKUP', true),

    // Databases to include in the backup (mysqldump will be used)
    'databases' => [
        [
            'name' => env('DB_DATABASE', 'dtr_system'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
        ],
        [
            'name' => env('ZK_DB_DATABASE', 'zkbiotime'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
        ],
    ],
];
