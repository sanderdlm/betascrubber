<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use App\Service\StorageService;
use App\Service\VideoDownloadService;
use App\Service\VideoSplitService;
use App\Service\S3VideoStorageManager;
use App\Service\LocalVideoStorageManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../public');
$dotenv->safeLoad();

// Get arguments
$videoUrl = $argv[1] ?? '';
$videoHash = $argv[2] ?? '';
$tmpDir = $argv[3] ?? '';

// Initialize services based on environment
$appEnv = $_ENV['APP_ENV'] ?? 'production';

// Initialize Logger
$logger = new Logger('process_video');
if (strtolower($appEnv) === 'dev') {
    $logger->pushHandler(new RotatingFileHandler(__DIR__ . '/../logs/process_video.log'));
} else {
    $logger->pushHandler(new StreamHandler('php://stdout'));
}

if (strtolower($appEnv) === 'dev') {
    $storageManager = new LocalVideoStorageManager($tmpDir);
} else {
    $storageService = new StorageService();
    $storageManager = new S3VideoStorageManager($storageService, $tmpDir);
}

$downloadService = new VideoDownloadService();
$splitService = new VideoSplitService();

if (empty($videoUrl) || empty($videoHash) || empty($tmpDir)) {
    $storageManager->setStatus($videoHash, 'error');
    exit(1);
}

try {
    // Update status
    $storageManager->setStatus($videoHash, 'processing');
    $logger->info('Starting video processing', ['hash' => $videoHash, 'url' => $videoUrl]);

    // Download video and get title
    $videoTitle = $downloadService->getVideoTitle($videoUrl);
    $sanitizedTitle = $downloadService->sanitizeTitle($videoTitle);
    $sanitizedTitle = substr($sanitizedTitle, 0, 100);
    $logger->info('Retrieved video title', ['title' => $videoTitle, 'sanitized' => $sanitizedTitle]);

    // Get video file path and download
    $videoPath = $storageManager->getVideoFilePath($videoHash, $sanitizedTitle);
    $logger->info('Downloading video', ['path' => $videoPath]);
    $downloadService->downloadVideo($videoUrl, $videoPath);
    $logger->info('Video downloaded successfully');

    // Create directories
    $dirs = $storageManager->createDirectories($videoHash, $sanitizedTitle);
    $framesDir = $dirs['frames'];
    $logger->info('Created directories', ['frames_dir' => $framesDir]);

    // Split video into frames
    $logger->info('Splitting video into frames');
    $frames = $splitService->split($videoPath, $framesDir);
    $logger->info('Video split completed', ['frame_count' => count($frames)]);

    // Upload frames to S3 (production only, local keeps them on filesystem)
    $framesUploaded = $storageManager->uploadFrames($videoHash, $framesDir);
    if ($framesUploaded > 0) {
        $logger->info('Frames uploaded to S3', ['count' => $framesUploaded]);
    } else {
        $logger->info('Frames kept in local filesystem', ['count' => count($frames)]);
    }

    // Clean up video file (keep frames for selection step)
    if (file_exists($videoPath)) {
        unlink($videoPath);
        $logger->info('Cleaned up video file', ['path' => $videoPath]);
    }

    // Mark as completed
    $storageManager->setStatus($videoHash, 'completed');
    $logger->info('Video processing completed successfully', ['hash' => $videoHash]);
    exit(0);

} catch (Exception $e) {
    $errorMessage = $e->getMessage();

    $logger->error('Video processing failed', [
        'hash' => $videoHash,
        'url' => $videoUrl,
        'error' => $errorMessage,
        'trace' => $e->getTraceAsString()
    ]);

    // Write error status with message
    $storageManager->setStatus($videoHash, 'error: ' . $errorMessage);
    exit(1);
}
