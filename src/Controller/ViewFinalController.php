<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StorageService;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final readonly class ViewFinalController implements ControllerInterface
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

        // Get final frames from S3
        $metadata = $this->storage->getFramesMetadata($id, 'final');

        if (!$metadata['exists']) {
            return new HtmlResponse($this->twig->load('view_final.twig')->render([
                'id' => $id,
                'video_title' => null,
                'frames' => [],
                'frame_urls' => [],
            ]));
        }

        // Build frame URLs
        $frameUrls = [];
        foreach ($metadata['frames'] as $frame) {
            $frameUrls[$frame] = $this->storage->getPublicUrl("{$id}/final/{$frame}");
        }

        return new HtmlResponse($this->twig->load('view_final.twig')->render([
            'id' => $id,
            'video_title' => $metadata['title'],
            'frames' => $metadata['frames'],
            'frame_urls' => $frameUrls,
        ]));
    }
}
