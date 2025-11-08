<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StorageService;
use App\Service\VideoStorageManagerInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final readonly class ViewFramesController implements ControllerInterface
{
    public function __construct(
        private Environment $twig,
        private StorageService $storage,
        private VideoStorageManagerInterface $storageManager
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // Get the video hash from URL parameter
        $videoHash = $request->getAttribute('id');

        // Handle POST request - process frame selection
        if ($request->getMethod() === 'POST') {
            return $this->handleFrameSelection($request, $videoHash);
        }

        // Handle GET request - show frame selection UI
        return $this->showFrameSelection($request, $videoHash);
    }

    private function showFrameSelection(ServerRequestInterface $request, string $videoHash): ResponseInterface
    {
        // Get frames metadata
        $metadata = $this->storageManager->getFramesMetadata($videoHash, 'frames');

        if (empty($metadata['frames'])) {
            return new HtmlResponse($this->twig->load('view_frames.twig')->render([
                'id' => $videoHash,
                'video_title' => null,
                'frames' => [],
                'storage' => $this->storage,
            ]));
        }

        // Build frame URLs for display
        $frameUrls = [];
        foreach ($metadata['frames'] as $frameData) {
            $frameName = $frameData['filename'];
            $frameUrls[$frameName] = $frameData['url'];
        }

        return new HtmlResponse($this->twig->load('view_frames.twig')->render([
            'id' => $videoHash,
            'video_title' => $metadata['metadata']['title'] ?? null,
            'frames' => array_column($metadata['frames'], 'filename'),
            'frame_urls' => $frameUrls,
            'storage' => $this->storage,
        ]));
    }

    private function handleFrameSelection(ServerRequestInterface $request, string $videoHash): ResponseInterface
    {
        // Get selected frames from POST data
        $parsedBody = $request->getParsedBody();
        $selectedFrames = $parsedBody['selected_frames'] ?? [];

        if (empty($selectedFrames)) {
            // Redirect back if no frames selected
            return new RedirectResponse('/view-frames/' . $videoHash);
        }

        // Get all frames
        $metadata = $this->storageManager->getFramesMetadata($videoHash, 'frames');
        $allFrames = array_column($metadata['frames'], 'filename');

        // Move selected frames to final folder
        $this->storageManager->moveToFinal($videoHash, $selectedFrames);

        // Delete all frames (both selected and unselected) from frames folder
        $this->storageManager->deleteFrames($videoHash, $allFrames);

        // Redirect to view final frames
        return new RedirectResponse('/view-final/' . $videoHash);
    }
}
