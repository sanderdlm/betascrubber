<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StorageService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final readonly class ViewFramesController implements ControllerInterface
{
    public function __construct(
        private Environment $twig,
        private StorageService $storage
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // Get the beta ID from URL parameter
        $id = $request->getAttribute('id');

        // Handle POST request - process frame selection
        if ($request->getMethod() === 'POST') {
            return $this->handleFrameSelection($request, $id);
        }

        // Handle GET request - show frame selection UI
        return $this->showFrameSelection($request, $id);
    }

    private function showFrameSelection(ServerRequestInterface $request, string $id): ResponseInterface
    {
        // Get frames from S3
        $metadata = $this->storage->getFramesMetadata($id, 'frames');

        if (!$metadata['exists']) {
            return new HtmlResponse($this->twig->load('view_frames.twig')->render([
                'id' => $id,
                'video_title' => null,
                'frames' => [],
                'storage' => $this->storage,
            ]));
        }

        // Build frame URLs for display
        $frameUrls = [];
        foreach ($metadata['frames'] as $frame) {
            $frameUrls[$frame] = $this->storage->getPublicUrl("{$id}/frames/{$frame}");
        }

        return new HtmlResponse($this->twig->load('view_frames.twig')->render([
            'id' => $id,
            'video_title' => $metadata['title'],
            'frames' => $metadata['frames'],
            'frame_urls' => $frameUrls,
            'storage' => $this->storage,
        ]));
    }

    private function handleFrameSelection(ServerRequestInterface $request, string $id): ResponseInterface
    {
        // Get selected frames from POST data
        $parsedBody = $request->getParsedBody();
        $selectedFrames = $parsedBody['selected_frames'] ?? [];

        if (empty($selectedFrames)) {
            // Redirect back if no frames selected
            return new RedirectResponse('/view-frames/' . $id);
        }

        // Get all frames from S3
        $metadata = $this->storage->getFramesMetadata($id, 'frames');
        $allFrames = $metadata['frames'];

        // Copy selected frames to 'final' folder and delete unselected ones in S3
        $framesToDelete = [];

        foreach ($allFrames as $frame) {
            $sourceKey = "{$id}/frames/{$frame}";

            if (in_array($frame, $selectedFrames)) {
                // Copy to final folder
                $finalKey = "{$id}/final/{$frame}";
                $this->storage->copyObject($sourceKey, $finalKey);

                // Mark source for deletion
                $framesToDelete[] = $sourceKey;
            } else {
                // Mark unselected frame for deletion
                $framesToDelete[] = $sourceKey;
            }
        }

        // Delete frames from S3
        if (!empty($framesToDelete)) {
            $this->storage->deleteObjects($framesToDelete);
        }

        // Redirect to view final frames
        return new RedirectResponse('/view-final/' . $id);
    }
}
