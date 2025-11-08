<?php

declare(strict_types=1);

namespace App\Service;

class LocalVideoStorageManager implements VideoStorageManagerInterface
{
    public function __construct(
        private string $tmpDir
    ) {
    }

    public function createDirectories(string $videoHash, string $title): array
    {
        $prefix = $videoHash . '___' . $title;
        $framesDir = $this->tmpDir . '/' . $prefix . '_frames';
        $finalDir = $this->tmpDir . '/' . $prefix . '_final';

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
        $pattern = $this->tmpDir . '/' . $videoHash . '___*_frames';
        $dirs = glob($pattern);

        if (!is_array($dirs) || count($dirs) === 0) {
            return false;
        }

        $framesDir = $dirs[0];
        $frames = glob($framesDir . '/*.jpg');

        return is_array($frames) && count($frames) > 0;
    }

    public function finalExists(string $videoHash): bool
    {
        $pattern = $this->tmpDir . '/' . $videoHash . '___*_final';
        $dirs = glob($pattern);

        if (!is_array($dirs) || count($dirs) === 0) {
            return false;
        }

        $finalDir = $dirs[0];
        $frames = glob($finalDir . '/*.jpg');

        return is_array($frames) && count($frames) > 0;
    }

    public function uploadFrames(string $videoHash, string $framesDir): int
    {
        return count(glob($framesDir . '/*.jpg'));
    }

    public function moveToFinal(string $videoHash, array $selectedFrames): int
    {
        $framesDir = $this->getFramesDir($videoHash);
        $finalDir = $this->getFinalDir($videoHash);

        if (!$framesDir || !$finalDir) {
            throw new \RuntimeException('Frames or final directory not found for ' . $videoHash);
        }

        $movedCount = 0;
        foreach ($selectedFrames as $frame) {
            $sourcePath = $framesDir . '/' . $frame;
            $destPath = $finalDir . '/' . $frame;

            if (file_exists($sourcePath)) {
                copy($sourcePath, $destPath);
                $movedCount++;
            }
        }

        return $movedCount;
    }

    public function deleteFrames(string $videoHash, array $framesToDelete): int
    {
        if (empty($framesToDelete)) {
            return 0;
        }

        $framesDir = $this->getFramesDir($videoHash);
        if (!$framesDir) {
            return 0;
        }

        $deletedCount = 0;
        foreach ($framesToDelete as $frame) {
            $framePath = $framesDir . '/' . $frame;
            if (file_exists($framePath)) {
                unlink($framePath);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    public function getFramesMetadata(string $videoHash, string $folder = 'frames'): array
    {
        $dir = $folder === 'frames'
            ? $this->getFramesDir($videoHash)
            : $this->getFinalDir($videoHash);

        if (!$dir) {
            return [
                'frames' => [],
                'metadata' => null,
            ];
        }

        $frames = glob($dir . '/*.jpg');
        if ($frames === false) {
            $frames = [];
        }

        $frameData = [];
        foreach ($frames as $framePath) {
            $filename = basename($framePath);
            $dirBasename = basename($dir);
            $frameData[] = [
                'key' => $filename,
                'url' => '/tmp/' . $dirBasename . '/' . $filename,
                'filename' => $filename,
            ];
        }

        return [
            'frames' => $frameData,
            'metadata' => null,
        ];
    }

    public function getStatusFilePath(string $videoHash): string
    {
        return $this->tmpDir . '/' . $videoHash . '_status';
    }

    public function getVideoFilePath(string $videoHash, string $title): string
    {
        return $this->tmpDir . '/' . $videoHash . '___' . $title . '.mp4';
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
        if (!is_dir($this->tmpDir)) {
            return [];
        }

        $dirs = scandir($this->tmpDir);
        $finalDirs = [];

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $fullPath = $this->tmpDir . '/' . $dir;
            if (is_dir($fullPath) && str_ends_with($dir, '_final')) {
                $finalDirs[] = [
                    'path' => $fullPath,
                    'name' => $dir,
                    'mtime' => filemtime($fullPath)
                ];
            }
        }

        usort($finalDirs, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        $finalDirs = array_slice($finalDirs, 0, $limit);

        $recentVideos = [];
        foreach ($finalDirs as $dirInfo) {
            $dirName = str_replace('_final', '', $dirInfo['name']);
            $parts = explode('___', $dirName, 2);
            $hash = $parts[0];
            $title = isset($parts[1]) ? str_replace('_', ' ', $parts[1]) : 'Untitled';

            $thumbnail = $this->findFirstImage($dirInfo['path']);

            if ($thumbnail) {
                $recentVideos[] = [
                    'hash' => $hash,
                    'title' => $title,
                    'folder' => $dirName,
                    'thumbnail' => $thumbnail
                ];
            }
        }

        return $recentVideos;
    }

    private function findFirstImage(string $directory): ?string
    {
        $frames = scandir($directory);
        foreach ($frames as $frame) {
            $ext = pathinfo($frame, PATHINFO_EXTENSION);
            if ($ext === 'jpg' || $ext === 'png') {
                return $frame;
            }
        }
        return null;
    }

    private function getFramesDir(string $videoHash): ?string
    {
        $pattern = $this->tmpDir . '/' . $videoHash . '___*_frames';
        $dirs = glob($pattern);

        return (is_array($dirs) && count($dirs) > 0) ? $dirs[0] : null;
    }

    private function getFinalDir(string $videoHash): ?string
    {
        $pattern = $this->tmpDir . '/' . $videoHash . '___*_final';
        $dirs = glob($pattern);

        return (is_array($dirs) && count($dirs) > 0) ? $dirs[0] : null;
    }
}
