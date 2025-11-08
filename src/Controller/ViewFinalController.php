<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\VideoStorageManagerInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final readonly class ViewFinalController implements ControllerInterface
{
    public function __construct(
        private Environment $twig,
        private VideoStorageManagerInterface $storageManager
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // Get the video hash from URL parameter
        $videoHash = $request->getAttribute('id');

        // Get final frames metadata
        $metadata = $this->storageManager->getFramesMetadata($videoHash, 'final');

        if (empty($metadata['frames'])) {
            return new HtmlResponse($this->twig->load('view_final.twig')->render([
                'id' => $videoHash,
                'video_title' => null,
                'frames' => [],
                'frame_urls' => [],
            ]));
        }

        // Build frame URLs
        $frameUrls = [];
        foreach ($metadata['frames'] as $frameData) {
            $frameName = $frameData['filename'];
            $frameUrls[$frameName] = $frameData['url'];
        }

        return new HtmlResponse($this->twig->load('view_final.twig')->render([
            'id' => $videoHash,
            'video_title' => $metadata['metadata']['title'] ?? null,
            'frames' => array_column($metadata['frames'], 'filename'),
            'frame_urls' => $frameUrls,
        ]));
    }
}
