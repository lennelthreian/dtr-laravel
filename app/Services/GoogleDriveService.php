<?php

namespace App\Services;

require_once base_path('vendor/google/apiclient/src/Google/autoload.php');

class GoogleDriveService
{
    protected $client;
    protected $service;
    protected $folderId;

    public function __construct()
    {
        $this->client = new \Google_Client();
        $this->client->setApplicationName('e-DTR Backup');
        $this->client->setScopes([\Google_Service_Drive::DRIVE_FILE]);
        $this->client->setAccessType('offline');

        $credentialsPath = config('google-drive.service_account_credentials');

        if (file_exists($credentialsPath)) {
            $this->client->setAuthConfig($credentialsPath);
        }

        $this->service = new \Google_Service_Drive($this->client);
        $this->folderId = config('google-drive.folder_id');
    }

    public function uploadFile($filePath, $fileName)
    {
        $fileMetadata = new \Google_Service_Drive_DriveFile([
            'name' => $fileName,
        ]);

        if ($this->folderId) {
            $fileMetadata->setParents([$this->folderId]);
        }

        $content = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        $file = $this->service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
        ]);

        return $file;
    }

    public function deleteFile($fileId)
    {
        $this->service->files->delete($fileId);
    }

    public function cleanupOldBackups()
    {
        $maxBackups = config('google-drive.max_backups', 7);

        $query = "name contains 'backup-'";
        if ($this->folderId) {
            $query .= " and '{$this->folderId}' in parents";
        }

        $files = $this->service->files->listFiles([
            'q' => $query,
            'orderBy' => 'createdTime desc',
            'pageSize' => 100,
        ]);

        $backups = iterator_to_array($files);

        if (count($backups) > $maxBackups) {
            $toDelete = array_slice($backups, $maxBackups);
            foreach ($toDelete as $file) {
                $this->service->files->delete($file->getId());
            }
        }
    }

    public function isConfigured()
    {
        $credentialsPath = config('google-drive.service_account_credentials');
        return file_exists($credentialsPath);
    }
}
