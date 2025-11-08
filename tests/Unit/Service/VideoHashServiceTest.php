<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\VideoHashService;
use PHPUnit\Framework\TestCase;

final class VideoHashServiceTest extends TestCase
{
    private VideoHashService $hashService;

    protected function setUp(): void
    {
        $this->hashService = new VideoHashService();
    }

    public function testUrlToHashGeneratesValidHash(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $hash = $this->hashService->urlToHash($url);

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
        $this->assertStringNotContainsString('+', $hash);
        $this->assertStringNotContainsString('/', $hash);
    }

    public function testHashToUrlDecodesCorrectly(): void
    {
        $originalUrl = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $hash = $this->hashService->urlToHash($originalUrl);
        $decodedUrl = $this->hashService->hashToUrl($hash);

        $this->assertEquals($originalUrl, $decodedUrl);
    }

    public function testBidirectionalConversionWithVariousUrls(): void
    {
        $urls = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/NTI1zXScbKI',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=abc123&t=10s',
        ];

        foreach ($urls as $url) {
            $hash = $this->hashService->urlToHash($url);
            $decoded = $this->hashService->hashToUrl($hash);

            $this->assertEquals($url, $decoded, "Failed for URL: $url");
        }
    }

    public function testSameUrlGeneratesSameHash(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        $hash1 = $this->hashService->urlToHash($url);
        $hash2 = $this->hashService->urlToHash($url);

        $this->assertEquals($hash1, $hash2);
    }

    public function testDifferentUrlsGenerateDifferentHashes(): void
    {
        $url1 = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        $url2 = 'https://www.youtube.com/watch?v=abc123def';

        $hash1 = $this->hashService->urlToHash($url1);
        $hash2 = $this->hashService->urlToHash($url2);

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testHashToUrlThrowsExceptionForInvalidHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->hashService->hashToUrl('invalid!!!hash');
    }

    public function testHashIsFilesystemSafe(): void
    {
        $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&feature=share';
        $hash = $this->hashService->urlToHash($url);

        // Check that hash doesn't contain characters problematic for filesystems
        $this->assertDoesNotMatchRegularExpression('/[\/\\\\:*?"<>|]/', $hash);
    }
}
