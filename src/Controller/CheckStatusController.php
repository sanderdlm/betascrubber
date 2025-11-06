<?php

declare(strict_types=1);

namespace App\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CheckStatusController implements ControllerInterface
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $videoHash = $request->getAttribute('hash');
        $tmpDir = __DIR__ . '/../../public/tmp';
        $statusFile = $tmpDir . '/' . $videoHash . '_status';

        // Check status file
        $status = 'processing';

        if (file_exists($statusFile)) {
            $fileStatus = trim(file_get_contents($statusFile));
            if ($fileStatus === 'completed' || $fileStatus === 'error') {
                $status = $fileStatus;
                // Clean up status file after reading
                if ($fileStatus === 'error') {
                    unlink($statusFile);
                }
            }
        } else {
            // Fallback: check if frames directory exists
            if (is_dir($tmpDir)) {
                $dirs = scandir($tmpDir);
                foreach ($dirs as $dir) {
                    if (str_starts_with($dir, $videoHash . '_frames') || str_starts_with($dir, $videoHash . '___')) {
                        if (str_ends_with($dir, '_frames')) {
                            $status = 'completed';
                            break;
                        }
                    }
                }
            }
        }

        return new JsonResponse(['status' => $status]);
    }
}
