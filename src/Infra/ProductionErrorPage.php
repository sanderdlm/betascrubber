<?php

namespace App\Infra;

use Exception;
use Middlewares\Utils\Factory;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Twig\Environment;

readonly class ProductionErrorPage implements StreamFactoryInterface
{
    public function __construct(
        private Environment $twig
    ) {
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return Factory::createStream($this->twig->load('production_error.twig')->render());
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        // This is safe as the Middleware only uses createStream()
        throw new Exception('Not implemented');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        // This is safe as the Middleware only uses createStream()
        throw new Exception('Not implemented');
    }
}