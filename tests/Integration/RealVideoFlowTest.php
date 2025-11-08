<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\VideoHashService;
use App\Service\LocalVideoStorageManager;
use PHPUnit\Framework\TestCase;

final class RealVideoFlowTest extends TestCase
{
    private const string TEST_VIDEO_URL = 'https://www.youtube.com/shorts/NTI1zXScbKI';
    private const string EXPECTED_HASH = 'aHR0cHM6Ly93d3cueW91dHViZS5jb20vc2hvcnRzL05USTF6WFNjYktJ';

    private VideoHashService $hashService;
    private LocalVideoStorageManager $storageManager;
    private string $tmpTestDir;

    protected function setUp(): void
    {
        $this->hashService = new VideoHashService();
        $this->tmpTestDir = sys_get_temp_dir() . '/betascrubber_real_test_' . uniqid();
        mkdir($this->tmpTestDir, 0777, true);
        $this->storageManager = new LocalVideoStorageManager($this->tmpTestDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpTestDir);
    }

    public function testHashGenerationForRealUrl(): void
    {
        $hash = $this->hashService->urlToHash(self::TEST_VIDEO_URL);

        $this->assertEquals(self::EXPECTED_HASH, $hash);
    }

    public function testHashIsBidirectionalForRealUrl(): void
    {
        $hash = $this->hashService->urlToHash(self::TEST_VIDEO_URL);
        $decodedUrl = $this->hashService->hashToUrl($hash);

        $this->assertEquals(self::TEST_VIDEO_URL, $decodedUrl);
    }

    public function testDuplicateDetectionWhenFramesExist(): void
    {
        $hash = self::EXPECTED_HASH;

        $this->assertFalse($this->storageManager->framesExist($hash));

        $framesDir = $this->tmpTestDir . '/' . $hash . '___La_Statique_5_Franchard_Isatis_bouldering_fontainebleau_climbing_frames';
        mkdir($framesDir, 0777, true);
        touch($framesDir . '/frame_0001.jpg');
        touch($framesDir . '/frame_0002.jpg');

        $this->assertTrue($this->storageManager->framesExist($hash));
    }

    public function testDuplicateDetectionWhenFinalExists(): void
    {
        $hash = self::EXPECTED_HASH;

        $this->assertFalse($this->storageManager->finalExists($hash));

        $finalDir = $this->tmpTestDir . '/' . $hash . '___La_Statique_5_Franchard_Isatis_bouldering_fontainebleau_climbing_final';
        mkdir($finalDir, 0777, true);
        touch($finalDir . '/frame_0001.jpg');

        $this->assertTrue($this->storageManager->finalExists($hash));
    }

    public function testFolderStructureMatchesExpectedPattern(): void
    {
        $hash = self::EXPECTED_HASH;
        $title = 'La_Statique_5_Franchard_Isatis_bouldering_fontainebleau_climbing';

        $dirs = $this->storageManager->createDirectories($hash, $title);

        $this->assertStringContainsString($hash, $dirs['frames']);
        $this->assertStringContainsString($title, $dirs['frames']);
        $this->assertStringEndsWith('_frames', $dirs['frames']);

        $this->assertStringContainsString($hash, $dirs['final']);
        $this->assertStringContainsString($title, $dirs['final']);
        $this->assertStringEndsWith('_final', $dirs['final']);
    }

    public function testGlobPatternFindsRealHashFolder(): void
    {
        $hash = self::EXPECTED_HASH;

        $framesDir = $this->tmpTestDir . '/' . $hash . '___La_Statique_5_Franchard_Isatis_bouldering_fontainebleau_climbing_frames';
        mkdir($framesDir, 0777, true);
        touch($framesDir . '/frame_0001.jpg');

        $pattern = $this->tmpTestDir . '/' . $hash . '___*_frames';
        $matches = glob($pattern);

        $this->assertCount(1, $matches);
        $this->assertEquals($framesDir, $matches[0]);
    }

    public function testStatusFilePathUsesCorrectHash(): void
    {
        $hash = self::EXPECTED_HASH;

        $statusPath = $this->storageManager->getStatusFilePath($hash);

        $this->assertStringContainsString($hash, $statusPath);
        $this->assertStringEndsWith('_status', $statusPath);
    }

    public function testVideoFilePathUsesCorrectHash(): void
    {
        $hash = self::EXPECTED_HASH;
        $title = 'Test_Video';

        $videoPath = $this->storageManager->getVideoFilePath($hash, $title);

        $this->assertStringContainsString($hash, $videoPath);
        $this->assertStringContainsString($title, $videoPath);
        $this->assertStringEndsWith('.mp4', $videoPath);
    }

    public function testRecentVideosIncludesRealHashVideo(): void
    {
        $hash = self::EXPECTED_HASH;

        $finalDir = $this->tmpTestDir . '/' . $hash . '___La_Statique_5_final';
        mkdir($finalDir, 0777, true);
        touch($finalDir . '/frame_0001.jpg');

        $recentVideos = $this->storageManager->getRecentVideos();

        $this->assertCount(1, $recentVideos);
        $this->assertEquals($hash, $recentVideos[0]['hash']);
        $this->assertEquals('La Statique 5', $recentVideos[0]['title']);
    }

    public function testCompleteWorkflow(): void
    {
        $hash = self::EXPECTED_HASH;

        $this->assertFalse($this->storageManager->framesExist($hash));
        $this->assertFalse($this->storageManager->finalExists($hash));

        $dirs = $this->storageManager->createDirectories($hash, 'Test_Video');
        touch($dirs['frames'] . '/frame_0001.jpg');
        touch($dirs['frames'] . '/frame_0002.jpg');
        touch($dirs['frames'] . '/frame_0003.jpg');

        $this->assertTrue($this->storageManager->framesExist($hash));
        $this->assertFalse($this->storageManager->finalExists($hash));

        $this->storageManager->moveToFinal($hash, ['frame_0001.jpg', 'frame_0003.jpg']);

        $this->assertTrue($this->storageManager->finalExists($hash));

        $finalMetadata = $this->storageManager->getFramesMetadata($hash, 'final');
        $this->assertCount(2, $finalMetadata['frames']);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
