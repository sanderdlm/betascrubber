<?php

declare(strict_types=1);

namespace App\Controller;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Twig\Environment;

final readonly class EditBetaController implements ControllerInterface
{
    public function __construct(
        private Environment $twig
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // Get the beta ID from URL parameter (may be empty for new videos)
        $id = $request->getAttribute('id');

        // If it's a GET request, show the form
        if ($request->getMethod() === 'GET') {
            // Get last 5 processed boulders
            $recentBoulders = [];
            $tmpDir = __DIR__ . '/../../public/tmp';

            if (is_dir($tmpDir)) {
                $dirs = scandir($tmpDir);
                $finalDirs = [];

                foreach ($dirs as $dir) {
                    if ($dir === '.' || $dir === '..') continue;

                    $fullPath = $tmpDir . '/' . $dir;
                    if (is_dir($fullPath) && str_ends_with($dir, '_final')) {
                        $finalDirs[] = [
                            'path' => $fullPath,
                            'name' => $dir,
                            'mtime' => filemtime($fullPath)
                        ];
                    }
                }

                // Sort by modification time (newest first)
                usort($finalDirs, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

                // Get last 5
                $finalDirs = array_slice($finalDirs, 0, 5);

                foreach ($finalDirs as $dirInfo) {
                    $dirName = str_replace('_final', '', $dirInfo['name']);
                    $parts = explode('___', $dirName, 2);
                    $hash = $parts[0];
                    $title = isset($parts[1]) ? str_replace('_', ' ', $parts[1]) : 'Untitled';

                    // Get first frame for thumbnail
                    $frames = scandir($dirInfo['path']);
                    $thumbnail = null;
                    foreach ($frames as $frame) {
                        if (pathinfo($frame, PATHINFO_EXTENSION) === 'png') {
                            $thumbnail = $frame;
                            break;
                        }
                    }

                    if ($thumbnail) {
                        $recentBoulders[] = [
                            'hash' => $hash,
                            'title' => $title,
                            'folder' => $dirName,
                            'thumbnail' => $thumbnail
                        ];
                    }
                }
            }

            return new HtmlResponse($this->twig->load('edit_beta.twig')->render([
                'id' => $id,
                'recent_boulders' => $recentBoulders
            ]));
        }

        // Handle POST request - process the video
        $parsedBody = $request->getParsedBody();
        $videoUrl = $parsedBody['video_url'] ?? '';

        if (empty($videoUrl)) {
            return new HtmlResponse($this->twig->load('edit_beta.twig')->render([
                'id' => $id,
                'error' => 'Please provide a YouTube URL'
            ]));
        }

        // Generate hash from video URL to use as ID
        $videoHash = substr(md5($videoUrl), 0, 12);

        // Check if final frames already exist for this video (already processed)
        $tmpDir = __DIR__ . '/../../public/tmp';
        if (is_dir($tmpDir)) {
            $dirs = scandir($tmpDir);
            foreach ($dirs as $dir) {
                if (str_starts_with($dir, $videoHash . '_final') || str_starts_with($dir, $videoHash . '___')) {
                    if (str_ends_with($dir, '_final')) {
                        // Final frames already exist, redirect directly to view final
                        return new RedirectResponse('/view-final/' . $videoHash);
                    }
                }
            }
            // Check if frames exist but haven't been selected yet
            foreach ($dirs as $dir) {
                if (str_starts_with($dir, $videoHash . '_frames') || str_starts_with($dir, $videoHash . '___')) {
                    if (str_ends_with($dir, '_frames')) {
                        // Frames exist but not finalized, redirect to view frames
                        return new RedirectResponse('/view-frames/' . $videoHash);
                    }
                }
            }
        }

        // Create status file to indicate processing has started
        $statusFile = $tmpDir . '/' . $videoHash . '_status';
        file_put_contents($statusFile, 'processing');

        // Start background processing using shell with nohup to persist after request
        $scriptPath = __DIR__ . '/../../scripts/process_video.php';
        $logFile = $tmpDir . '/' . $videoHash . '_log.txt';

        // Use shell_exec with nohup to run in true background
        $command = sprintf(
            'nohup php %s %s %s %s > %s 2>&1 &',
            escapeshellarg($scriptPath),
            escapeshellarg($videoUrl),
            escapeshellarg($videoHash),
            escapeshellarg($tmpDir),
            escapeshellarg($logFile)
        );
        shell_exec($command);

        // Redirect to processing page
        return new RedirectResponse('/processing/' . $videoHash);
    }
}