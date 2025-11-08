<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\LocalVideoStorageManager;
use PHPUnit\Framework\TestCase;

final class LocalVideoStorageManagerTest extends TestCase
{
    private LocalVideoStorageManager $storageManager;
    private string $tmpTestDir;

    protected function setUp(): void
    {
        $this->tmpTestDir = sys_get_temp_dir() . '/betascrubber_test_' . uniqid();
        mkdir($this->tmpTestDir, 0777, true);

        $this->storageManager = new LocalVideoStorageManager($this->tmpTestDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpTestDir);
    }

    public function testFramesExistReturnsFalseWhenNoFramesFolder(): void
    {
        $videoHash = 'test_hash_123';

        $result = $this->storageManager->framesExist($videoHash);

        $this->assertFalse($result);
    }

    public function testFramesExistReturnsFalseWhenFramesFolderIsEmpty(): void
    {
        $videoHash = 'test_hash_123';
        $framesDir = $this->tmpTestDir . '/' . $videoHash . '___Test_Video_frames';
        mkdir($framesDir, 0777, true);

        $result = $this->storageManager->framesExist($videoHash);

        $this->assertFalse($result);
    }

    public function testFramesExistReturnsTrueWhenFramesExist(): void
    {
        $videoHash = 'test_hash_123';
        $framesDir = $this->tmpTestDir . '/' . $videoHash . '___Test_Video_frames';
        mkdir($framesDir, 0777, true);

        touch($framesDir . '/frame_0001.jpg');
        touch($framesDir . '/frame_0002.jpg');

        $result = $this->storageManager->framesExist($videoHash);

        $this->assertTrue($result);
    }

    public function testFinalExistReturnsFalseWhenNoFinalFolder(): void
    {
        $videoHash = 'test_hash_123';

        $result = $this->storageManager->finalExists($videoHash);

        $this->assertFalse($result);
    }

    public function testFinalExistReturnsFalseWhenFinalFolderIsEmpty(): void
    {
        $videoHash = 'test_hash_123';
        $finalDir = $this->tmpTestDir . '/' . $videoHash . '___Test_Video_final';
        mkdir($finalDir, 0777, true);

        $result = $this->storageManager->finalExists($videoHash);

        $this->assertFalse($result);
    }

    public function testFinalExistReturnsTrueWhenFramesExist(): void
    {
        $videoHash = 'test_hash_123';
        $finalDir = $this->tmpTestDir . '/' . $videoHash . '___Test_Video_final';
        mkdir($finalDir, 0777, true);

        touch($finalDir . '/frame_0001.jpg');
        touch($finalDir . '/frame_0003.jpg');

        $result = $this->storageManager->finalExists($videoHash);

        $this->assertTrue($result);
    }

    public function testCreateDirectoriesCreatesFramesAndFinalDirs(): void
    {
        $videoHash = 'test_hash_456';
        $title = 'My_Test_Video';

        $dirs = $this->storageManager->createDirectories($videoHash, $title);

        $this->assertArrayHasKey('frames', $dirs);
        $this->assertArrayHasKey('final', $dirs);
        $this->assertDirectoryExists($dirs['frames']);
        $this->assertDirectoryExists($dirs['final']);
        $this->assertStringContainsString($videoHash, $dirs['frames']);
        $this->assertStringContainsString($title, $dirs['frames']);
    }

    public function testGlobPatternWorksWithBase64UrlHash(): void
    {
        $videoHash = 'aHR0cHM6Ly93d3cueW91dHViZS5jb20vc2hvcnRzL05USTF6WFNjYktJ';
        $framesDir = $this->tmpTestDir . '/' . $videoHash . '___La_Statique_frames';
        mkdir($framesDir, 0777, true);

        touch($framesDir . '/frame_0001.jpg');

        $result = $this->storageManager->framesExist($videoHash);

        $this->assertTrue($result);
    }

    public function testDuplicateCheckScenario(): void
    {
        $videoHash = 'aHR0cHM6Ly93d3cueW91dHViZS5jb20vc2hvcnRzL05USTF6WFNjYktJ';

        $this->assertFalse($this->storageManager->framesExist($videoHash));
        $this->assertFalse($this->storageManager->finalExists($videoHash));

        $framesDir = $this->tmpTestDir . '/' . $videoHash . '___Test_Video_frames';
        mkdir($framesDir, 0777, true);
        touch($framesDir . '/frame_0001.jpg');

        $this->assertTrue($this->storageManager->framesExist($videoHash));
        $this->assertFalse($this->storageManager->finalExists($videoHash));

        $finalDir = $this->tmpTestDir . '/' . $videoHash . '___Test_Video_final';
        mkdir($finalDir, 0777, true);
        touch($finalDir . '/frame_0001.jpg');

        $this->assertTrue($this->storageManager->framesExist($videoHash));
        $this->assertTrue($this->storageManager->finalExists($videoHash));
    }

    public function testGetRecentVideosReturnsEmptyWhenNoVideos(): void
    {
        $result = $this->storageManager->getRecentVideos();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetRecentVideosReturnsVideosOrderedByTime(): void
    {
        $video1Dir = $this->tmpTestDir . '/hash1___Video_One_final';
        mkdir($video1Dir, 0777, true);
        touch($video1Dir . '/frame_0001.jpg');
        touch($video1Dir, time() - 100);

        sleep(1);

        $video2Dir = $this->tmpTestDir . '/hash2___Video_Two_final';
        mkdir($video2Dir, 0777, true);
        touch($video2Dir . '/frame_0001.jpg');
        touch($video2Dir, time() - 50);

        sleep(1);

        $video3Dir = $this->tmpTestDir . '/hash3___Video_Three_final';
        mkdir($video3Dir, 0777, true);
        touch($video3Dir . '/frame_0001.jpg');

        $result = $this->storageManager->getRecentVideos(2);

        $this->assertCount(2, $result);
        $this->assertEquals('hash3', $result[0]['hash']);
        $this->assertEquals('Video Three', $result[0]['title']);
        $this->assertEquals('hash2', $result[1]['hash']);
    }

    public function testGetRecentVideosExtractsMetadataCorrectly(): void
    {
        $videoHash = 'aHR0cHM6Ly93d3cueW91dHViZS5jb20vc2hvcnRzL05USTF6WFNjYktJ';
        $finalDir = $this->tmpTestDir . '/' . $videoHash . '___La_Statique_5_final';
        mkdir($finalDir, 0777, true);
        touch($finalDir . '/frame_0001.jpg');
        touch($finalDir . '/frame_0002.jpg');

        $result = $this->storageManager->getRecentVideos();

        $this->assertCount(1, $result);
        $this->assertEquals($videoHash, $result[0]['hash']);
        $this->assertEquals('La Statique 5', $result[0]['title']);
        $this->assertEquals($videoHash . '___La_Statique_5', $result[0]['folder']);
        $this->assertEquals('frame_0001.jpg', $result[0]['thumbnail']);
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
