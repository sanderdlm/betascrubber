<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Process;
use RuntimeException;

class VideoSplitService
{
    private const int FFMPEG_TIMEOUT = 300; // 5 minutes
    private const int DEFAULT_FPS = 1;

    public function split(string $videoPath, string $outputDir, int $fps = self::DEFAULT_FPS): array
    {
        if (!file_exists($videoPath)) {
            throw new RuntimeException("Video file not found: {$videoPath}");
        }

        if (!is_dir($outputDir)) {
            if (!mkdir($outputDir, 0777, true)) {
                throw new RuntimeException("Failed to create output directory: {$outputDir}");
            }
        }

        $outputPattern = rtrim($outputDir, '/') . '/frame_%04d.jpg';

        $ffmpeg = new Process([
            'ffmpeg',
            '-i', $videoPath,
            '-vf', "fps={$fps},scale=-1:720:flags=fast_bilinear",
            '-q:v', '5',
            '-c:v', 'mjpeg',
            $outputPattern
        ]);

        $ffmpeg->setTimeout(self::FFMPEG_TIMEOUT);
        $ffmpeg->run();

        if (!$ffmpeg->isSuccessful()) {
            throw new RuntimeException(
                "Failed to split video: {$ffmpeg->getErrorOutput()}"
            );
        }

        // Get list of created frames
        $frames = glob($outputDir . '/frame_*.jpg');
        if ($frames === false) {
            throw new RuntimeException("Failed to read frames from output directory");
        }

        // Return just filenames, not full paths
        return array_map('basename', $frames);
    }
}
