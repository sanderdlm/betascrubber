<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\VideoStorageManagerInterface;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class EventStreamController implements ControllerInterface
{
    public function __construct(
        private VideoStorageManagerInterface $storageManager,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $videoHash = $queryParams['processId'] ?? '';

        if (empty($videoHash)) {
            return new Response\JsonResponse(['error' => 'Missing processId'], 400);
        }

        // Disable all output buffering
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Send headers immediately
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Start the event stream
        $maxAttempts = 120;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $status = $this->storageManager->getStatus($videoHash);

            if ($status === 'completed') {
                $this->logger->info('Video processing completed', ['hash' => $videoHash]);
                echo "data: " . json_encode([
                    'status' => 'completed',
                    'hash' => $videoHash
                ]) . "\n\n";
                flush();
                break;
            }

            if (($status !== null && $status !== 'processing')) {
                $this->logger->error('Video processing error', [
                    'hash' => $videoHash,
                    'status' => $status
                ]);
                echo "data: " . json_encode([
                    'status' => 'error',
                    'message' => $status
                ]) . "\n\n";
                flush();
                break;
            }

            echo "data: " . json_encode([
                'status' => 'processing',
                'attempt' => $attempt
            ]) . "\n\n";
            flush();

            sleep(2);
            $attempt++;
        }

        if ($attempt >= $maxAttempts) {
            echo "data: " . json_encode([
                'status' => 'timeout'
            ]) . "\n\n";
            flush();
        }

        exit;
    }
}
