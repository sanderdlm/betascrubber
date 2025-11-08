<?php

declare(strict_types=1);

namespace App\Service;

interface VideoStorageManagerInterface
{
    public function createDirectories(string $videoHash, string $title): array;

    public function framesExist(string $videoHash): bool;

    public function finalExists(string $videoHash): bool;

    public function uploadFrames(string $videoHash, string $framesDir): int;

    public function moveToFinal(string $videoHash, array $selectedFrames): int;

    public function deleteFrames(string $videoHash, array $framesToDelete): int;

    public function getFramesMetadata(string $videoHash, string $folder = 'frames'): array;

    public function getRecentVideos(int $limit = 5): array;

    public function getStatusFilePath(string $videoHash): string;

    public function getVideoFilePath(string $videoHash, string $title): string;

    public function getStatus(string $videoHash): ?string;

    public function setStatus(string $videoHash, string $status): void;

    public function startProcessing(string $videoUrl, string $videoHash): void;
}
