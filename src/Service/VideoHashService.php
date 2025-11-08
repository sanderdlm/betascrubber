<?php

declare(strict_types=1);

namespace App\Service;

final readonly class VideoHashService
{
    public function urlToHash(string $videoUrl): string
    {
        $encoded = base64_encode($videoUrl);

        return strtr($encoded, '+/', '-_');
    }

    public function hashToUrl(string $hash): string
    {
        $base64 = strtr($hash, '-_', '+/');

        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid hash provided');
        }

        return $decoded;
    }
}
