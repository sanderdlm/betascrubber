<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Service\StorageService;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Get arguments
$videoUrl = $argv[1] ?? '';
$videoHash = $argv[2] ?? '';
$tmpDir = $argv[3] ?? '';

$statusFile = $tmpDir . '/' . $videoHash . '_status';

if (empty($videoUrl) || empty($videoHash) || empty($tmpDir)) {
    file_put_contents($statusFile, 'error');
    exit(1);
}

try {
    // Update status
    file_put_contents($statusFile, 'processing');

    // Create tmp directory if it doesn't exist
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }

    // Get video title using youtube-dl
    $getTitleProcess = new Process([
        'youtube-dl',
        '--get-title',
        $videoUrl
    ]);
    $getTitleProcess->run();

    $videoTitle = '';
    if ($getTitleProcess->isSuccessful()) {
        $videoTitle = trim($getTitleProcess->getOutput());
        // Sanitize title for use in folder name
        $videoTitle = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $videoTitle);
        $videoTitle = preg_replace('/\s+/', '_', $videoTitle);
        $videoTitle = substr($videoTitle, 0, 100);
    }

    // Create folder name with hash and encoded title
    $folderName = $videoHash . ($videoTitle ? '___' . $videoTitle : '');

    // Download video using youtube-dl
    $videoPath = $tmpDir . '/' . $folderName . '.mp4';
    $youtubeDl = new Process([
        'yt-dlp',
        '-f', 'best',
        '-o', $videoPath,
        $videoUrl
    ]);
    $youtubeDl->setTimeout(300);
    $youtubeDl->run();

    if (!$youtubeDl->isSuccessful()) {
        throw new ProcessFailedException($youtubeDl);
    }

    // Create frames directory
    $framesDir = $tmpDir . '/' . $folderName . '_frames';
    if (!is_dir($framesDir)) {
        mkdir($framesDir, 0755, true);
    }

    // Extract 1 frame per second using ffmpeg
    $ffmpeg = new Process([
        'ffmpeg',
        '-i', $videoPath,
        '-vf', 'fps=1',
        $framesDir . '/frame_%04d.png'
    ]);
    $ffmpeg->setTimeout(300);
    $ffmpeg->run();

    if (!$ffmpeg->isSuccessful()) {
        throw new ProcessFailedException($ffmpeg);
    }

    // Upload frames to S3
    $storage = new StorageService();

    // Save metadata
    $storage->saveMetadata($videoHash, [
        'title' => $videoTitle,
        'url' => $videoUrl,
        'processed_at' => date('c'),
    ]);

    // Upload each frame to S3
    $uploadedFrames = [];
    if (is_dir($framesDir)) {
        $frameFiles = scandir($framesDir);
        foreach ($frameFiles as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'png') {
                $localPath = $framesDir . '/' . $file;
                $s3Key = "{$videoHash}/frames/{$file}";

                if ($storage->uploadFile($localPath, $s3Key, 'image/png')) {
                    $uploadedFrames[] = $file;
                    // Delete local file after successful upload
                    unlink($localPath);
                }
            }
        }
    }

    // Clean up local directories
    if (file_exists($videoPath)) {
        unlink($videoPath);
    }
    if (is_dir($framesDir)) {
        rmdir($framesDir);
    }

    // Mark as completed
    file_put_contents($statusFile, 'completed');
    exit(0);

} catch (Exception $e) {
    // Write error status
    file_put_contents($statusFile, 'error');
    exit(1);
}
