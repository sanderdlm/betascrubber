<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Process;
use RuntimeException;

class VideoDownloadService
{
    private const int DOWNLOAD_TIMEOUT = 300;
    private const int MAX_DURATION_SECONDS = 180; // 3 minutes

    public function downloadVideo(string $videoUrl, string $outputPath): string
    {
        // Get video title first
        $title = $this->getVideoTitle($videoUrl);

        // Download the video with optimized settings
        // Format: 480p or lower for speed, mp4 container
        $youtubeDl = new Process([
            'yt-dlp',
            '-f', 'bestvideo[height<=480][ext=mp4]+bestaudio[ext=m4a]/best[height<=480][ext=mp4]/best[height<=480]/best',
            '--merge-output-format', 'mp4',
            '-o', $outputPath,
            $videoUrl
        ]);

        $youtubeDl->setTimeout(self::DOWNLOAD_TIMEOUT);
        $youtubeDl->run();

        if (!$youtubeDl->isSuccessful()) {
            throw new RuntimeException(
                "Failed to download video: {$youtubeDl->getErrorOutput()}"
            );
        }

        return $title;
    }

    public function getVideoTitle(string $videoUrl): string
    {
        $getTitleProcess = new Process([
            'yt-dlp',
            '--get-title',
            $videoUrl
        ]);

        $getTitleProcess->run();

        if (!$getTitleProcess->isSuccessful()) {
            throw new RuntimeException(
                "Failed to get video title: {$getTitleProcess->getErrorOutput()}"
            );
        }

        return trim($getTitleProcess->getOutput());
    }

    public function sanitizeTitle(string $title): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9\s]/', '', $title);
        return preg_replace('/\s+/', '_', $sanitized);
    }

    public function getVideoDuration(string $videoUrl): int
    {
        $durationProcess = new Process([
            'yt-dlp',
            '--get-duration',
            $videoUrl
        ]);

        $durationProcess->run();

        if (!$durationProcess->isSuccessful()) {
            throw new RuntimeException(
                "Failed to get video duration: {$durationProcess->getErrorOutput()}"
            );
        }

        $durationStr = trim($durationProcess->getOutput());

        // Parse duration string (formats: "MM:SS" or "HH:MM:SS" or just "SS")
        $parts = array_reverse(explode(':', $durationStr));
        $seconds = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);
        $hours = (int) ($parts[2] ?? 0);

        return $seconds + ($minutes * 60) + ($hours * 3600);
    }

    public function checkDuration(string $videoUrl): void
    {
        $duration = $this->getVideoDuration($videoUrl);

        if ($duration > self::MAX_DURATION_SECONDS) {
            throw new RuntimeException(
                "Video is too long ({$duration}s). Maximum allowed duration is " . self::MAX_DURATION_SECONDS . " seconds (3 minutes)."
            );
        }
    }
}
