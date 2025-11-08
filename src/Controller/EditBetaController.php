<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\VideoStorageManagerInterface;
use App\Service\VideoDownloadService;
use App\Service\VideoHashService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final readonly class EditBetaController implements ControllerInterface
{
    public function __construct(
        private Environment $twig,
        private VideoStorageManagerInterface $storageManager,
        private VideoDownloadService $downloadService,
        private VideoHashService $hashService
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // If it's a GET request, show the form
        if ($request->getMethod() === 'GET') {
            return new HtmlResponse($this->twig->load('edit_beta.twig')->render([
                'recent_boulders' => $this->storageManager->getRecentVideos()
            ]));
        }

        // Handle POST request - process the video
        $parsedBody = $request->getParsedBody();
        $videoUrl = $parsedBody['video_url'] ?? '';

        if (empty($videoUrl)) {
            return new HtmlResponse($this->twig->load('edit_beta.twig')->render([
                'error' => 'Please provide a YouTube URL'
            ]));
        }

        // Generate hash from video URL FIRST (bidirectional encoding)
        $videoHash = $this->hashService->urlToHash($videoUrl);

        // IMMEDIATELY check for duplicates based on folder state
        // If final frames exist and folder is not empty -> redirect to scrubber
        if ($this->storageManager->finalExists($videoHash)) {
            return new RedirectResponse('/view-final/' . $videoHash);
        }

        // If frames exist and folder is not empty -> redirect to frame selection
        if ($this->storageManager->framesExist($videoHash)) {
            return new RedirectResponse('/view-frames/' . $videoHash);
        }

        // Check video duration before processing
        try {
            $this->downloadService->checkDuration($videoUrl);
        } catch (\RuntimeException $e) {
            return new HtmlResponse($this->twig->load('edit_beta.twig')->render([
                'error' => $e->getMessage(),
                'recent_boulders' => []
            ]));
        }

        // Start background processing
        $this->storageManager->startProcessing($videoUrl, $videoHash);

        // Redirect to homepage with processing notification
        return new RedirectResponse('/?processing=' . $videoHash);
    }
}