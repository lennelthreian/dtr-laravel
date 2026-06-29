<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GoogleDriveService;

class BackupDatabase extends Command
{
    protected $signature = 'backup:run';
    protected $description = 'Backup databases to Google Drive';

    public function handle(GoogleDriveService $driveService)
    {
        if (!$driveService->isConfigured()) {
            $this->error('Google Drive is not configured. Please upload the service-account.json file.');
            return 1;
        }

        $databases = config('google-drive.databases', []);
        $timestamp = now()->format('Y-m-d_H-i-s');
        $tempDir = storage_path('app/backups');

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $backupFiles = [];

        foreach ($databases as $db) {
            $dbName = $db['name'];
            $fileName = "backup-{$dbName}-{$timestamp}.sql";
            $filePath = "{$tempDir}/{$fileName}";

            $this->info("Dumping database: {$dbName}...");

            $command = sprintf(
                '"%s" --host=%s --port=%s --user=%s %s %s > "%s"',
                $this->getMysqldumpPath(),
                escapeshellarg($db['host']),
                escapeshellarg($db['port']),
                escapeshellarg($db['username']),
                !empty($db['password']) ? '--password=' . escapeshellarg($db['password']) : '',
                escapeshellarg($dbName),
                $filePath
            );

            $output = null;
            $returnCode = null;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $this->error("Failed to dump database: {$dbName}");
                continue;
            }

            if (!file_exists($filePath) || filesize($filePath) === 0) {
                $this->error("Backup file for {$dbName} is empty or missing.");
                continue;
            }

            $backupFiles[] = [
                'path' => $filePath,
                'name' => $fileName,
            ];

            $this->info("Dumped: {$fileName}");
        }

        if (empty($backupFiles)) {
            $this->error('No backup files were created.');
            return 1;
        }

        $this->info('Uploading to Google Drive...');

        foreach ($backupFiles as $file) {
            try {
                $driveService->uploadFile($file['path'], $file['name']);
                $this->info("Uploaded: {$file['name']}");
            } catch (\Exception $e) {
                $this->error("Failed to upload {$file['name']}: " . $e->getMessage());
            }

            @unlink($file['path']);
        }

        $this->info('Cleaning up old backups...');
        try {
            $driveService->cleanupOldBackups();
            $this->info('Old backups cleaned up.');
        } catch (\Exception $e) {
            $this->warn('Could not clean up old backups: ' . $e->getMessage());
        }

        $this->info('Backup completed successfully.');

        return 0;
    }

    protected function getMysqldumpPath()
    {
        $possiblePaths = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            'mysqldump',
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path) || $this->commandExists($path)) {
                return $path;
            }
        }

        return 'mysqldump';
    }

    protected function commandExists($cmd)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $result = shell_exec("where {$cmd} 2>nul");
            return !empty(trim($result));
        }
        $result = shell_exec("which {$cmd} 2>/dev/null");
        return !empty(trim($result));
    }
}
