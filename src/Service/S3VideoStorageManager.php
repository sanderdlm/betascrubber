<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

class S3VideoStorageManager implements VideoStorageManagerInterface
{
    public function __construct(
        private StorageService $storageService,
        private string $tmpDir
    ) {
    }

    public function createDirectories(string $videoHash, string $title): array
    {
        $prefix = $videoHash . '___' . $title;
        $framesDir = "{$this->tmpDir}/{$prefix}_frames";
        $finalDir = "{$this->tmpDir}/{$prefix}_final";

        if (!is_dir($framesDir)) {
            mkdir($framesDir, 0777, true);
        }

        if (!is_dir($finalDir)) {
            mkdir($finalDir, 0777, true);
        }

        return [
            'frames' => $framesDir,
            'final' => $finalDir,
        ];
    }

    public function framesExist(string $videoHash): bool
    {
        $objects = $this->storageService->listObjects("{$videoHash}/frames/");
        return count($objects) > 0;
    }

    public function finalExists(string $videoHash): bool
    {
        $objects = $this->storageService->listObjects("{$videoHash}/final/");
        return count($objects) > 0;
    }

    public function uploadFrames(string $videoHash, string $framesDir): int
    {
        $frames = glob("{$framesDir}/*.jpg");
        if ($frames === false) {
            throw new RuntimeException("Failed to list frames in {$framesDir}");
        }

        $uploadedCount = 0;
        foreach ($frames as $framePath) {
            $filename = basename($framePath);
            $key = "{$videoHash}/frames/{$filename}";

            $this->storageService->uploadFile($framePath, $key);
            $uploadedCount++;

            unlink($framePath);
        }

        return $uploadedCount;
    }

    public function moveToFinal(string $videoHash, array $selectedFrames): int
    {
        $movedCount = 0;
        foreach ($selectedFrames as $frame) {
            $sourceKey = "{$videoHash}/frames/{$frame}";
            $finalKey = "{$videoHash}/final/{$frame}";

            $this->storageService->copyObject($sourceKey, $finalKey);
            $movedCount++;
        }

        return $movedCount;
    }

    public function deleteFrames(string $videoHash, array $framesToDelete): int
    {
        if (empty($framesToDelete)) {
            return 0;
        }

        $keysToDelete = array_map(
            fn($frame) => "{$videoHash}/frames/{$frame}",
            $framesToDelete
        );

        $this->storageService->deleteObjects($keysToDelete);

        return count($keysToDelete);
    }

    public function getFramesMetadata(string $videoHash, string $folder = 'frames'): array
    {
        $prefix = "{$videoHash}/{$folder}/";
        $objects = $this->storageService->listObjects($prefix);

        $frames = [];
        foreach ($objects as $object) {
            $filename = basename($object['key']);
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $frames[] = [
                    'key' => $object['key'],
                    'url' => $this->storageService->getPublicUrl($object['key']),
                    'filename' => $filename,
                ];
            }
        }

        return [
            'frames' => $frames,
            'metadata' => null,
        ];
    }

    public function getStatusFilePath(string $videoHash): string
    {
        return $this->tmpDir . "/{$videoHash}_status";
    }

    public function getVideoFilePath(string $videoHash, string $title): string
    {
        return $this->tmpDir . "/{$videoHash}___{$title}.mp4";
    }

    public function getStatus(string $videoHash): ?string
    {
        $statusFile = $this->getStatusFilePath($videoHash);

        if (file_exists($statusFile)) {
            $status = trim(file_get_contents($statusFile));

            if ($status === 'error' || $status === 'completed') {
                unlink($statusFile);
            }

            return $status;
        }

        if ($this->finalExists($videoHash)) {
            return 'completed';
        }

        if ($this->framesExist($videoHash)) {
            return 'completed';
        }

        return null;
    }

    public function setStatus(string $videoHash, string $status): void
    {
        $statusFile = $this->getStatusFilePath($videoHash);
        file_put_contents($statusFile, $status);
    }

    public function startProcessing(string $videoUrl, string $videoHash): void
    {
        $this->setStatus($videoHash, 'processing');

        $scriptPath = __DIR__ . '/../../scripts/process_video.php';

        $command = sprintf(
            'nohup php %s %s %s %s > /dev/null 2>&1 &',
            escapeshellarg($scriptPath),
            escapeshellarg($videoUrl),
            escapeshellarg($videoHash),
            escapeshellarg($this->tmpDir)
        );

        shell_exec($command);
    }

    public function getRecentVideos(int $limit = 5): array
    {
        $allObjects = $this->storageService->listObjects('');
        $videosByHash = [];

        foreach ($allObjects as $object) {
            if (str_contains($object['key'], '/final/')) {
                $parts = explode('/', $object['key']);
                $hashAndTitle = $parts[0];

                if (!isset($videosByHash[$hashAndTitle])) {
                    $videosByHash[$hashAndTitle] = [
                        'mtime' => $object['last_modified'] ?? 0,
                        'files' => []
                    ];
                }

                $videosByHash[$hashAndTitle]['files'][] = $object['key'];
            }
        }

        uasort($videosByHash, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        $videosByHash = array_slice($videosByHash, 0, $limit, true);

        $recentVideos = [];
        foreach ($videosByHash as $hashAndTitle => $data) {
            $parts = explode('___', $hashAndTitle, 2);
            $hash = $parts[0];
            $title = isset($parts[1]) ? str_replace('_', ' ', $parts[1]) : 'Untitled';

            $thumbnail = null;
            foreach ($data['files'] as $key) {
                if (str_ends_with($key, '.jpg') || str_ends_with($key, '.png')) {
                    $thumbnail = basename($key);
                    break;
                }
            }

            if ($thumbnail) {
                $recentVideos[] = [
                    'hash' => $hash,
                    'title' => $title,
                    'folder' => $hashAndTitle,
                    'thumbnail' => $thumbnail
                ];
            }
        }

        return $recentVideos;
    }
}
