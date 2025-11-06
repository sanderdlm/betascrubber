<?php

declare(strict_types=1);

namespace App\Controller;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final readonly class ProcessingController implements ControllerInterface
{
    public function __construct(
        private Environment $twig
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $videoHash = $request->getAttribute('hash');

        return new HtmlResponse($this->twig->load('processing.twig')->render([
            'video_hash' => $videoHash
        ]));
    }
}
