<?php

declare(strict_types=1);

namespace App\Service;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

final class StorageService
{
    private S3Client $s3Client;
    private string $bucket;
    private string $endpoint;

    public function __construct()
    {
        $this->bucket = $_ENV['SPACES_BUCKET'] ?? '';
        $this->endpoint = $_ENV['SPACES_ENDPOINT'] ?? '';

        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $_ENV['SPACES_REGION'] ?? 'nyc3',
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => false,
            'credentials' => [
                'key' => $_ENV['SPACES_KEY'] ?? '',
                'secret' => $_ENV['SPACES_SECRET'] ?? '',
            ],
        ]);
    }

    /**
     * Upload a file to S3/Spaces
     */
    public function uploadFile(string $localPath, string $key, string $contentType = 'image/png'): bool
    {
        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SourceFile' => $localPath,
                'ACL' => 'public-read',
                'ContentType' => $contentType,
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('S3 Upload Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * List objects with a prefix
     */
    public function listObjects(string $prefix): array
    {
        try {
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ]);

            $objects = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $objects[] = [
                        'key' => $object['Key'],
                        'size' => $object['Size'],
                        'modified' => $object['LastModified'],
                    ];
                }
            }
            return $objects;
        } catch (AwsException $e) {
            error_log('S3 List Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete an object
     */
    public function deleteObject(string $key): bool
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('S3 Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete multiple objects
     */
    public function deleteObjects(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        try {
            $objects = array_map(fn($key) => ['Key' => $key], $keys);
            $this->s3Client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => ['Objects' => $objects],
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('S3 Delete Objects Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get public URL for an object
     */
    public function getPublicUrl(string $key): string
    {
        return sprintf('%s/%s/%s',
            rtrim($this->endpoint, '/'),
            $this->bucket,
            ltrim($key, '/')
        );
    }

    /**
     * Check if an object exists
     */
    public function objectExists(string $key): bool
    {
        try {
            $this->s3Client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * Get metadata about stored frames
     */
    public function getFramesMetadata(string $videoHash, string $type = 'frames'): array
    {
        $prefix = "{$videoHash}/{$type}/";
        $objects = $this->listObjects($prefix);

        $metadata = [
            'exists' => !empty($objects),
            'count' => count($objects),
            'frames' => [],
            'title' => null,
        ];

        foreach ($objects as $object) {
            $filename = basename($object['key']);
            if (pathinfo($filename, PATHINFO_EXTENSION) === 'png') {
                $metadata['frames'][] = $filename;
            } elseif ($filename === 'metadata.json') {
                // We can store title in metadata file
                try {
                    $result = $this->s3Client->getObject([
                        'Bucket' => $this->bucket,
                        'Key' => $object['key'],
                    ]);
                    $data = json_decode($result['Body'], true);
                    $metadata['title'] = $data['title'] ?? null;
                } catch (AwsException $e) {
                    // Ignore
                }
            }
        }

        sort($metadata['frames']);
        return $metadata;
    }

    /**
     * Save metadata for a video
     */
    public function saveMetadata(string $videoHash, array $metadata): bool
    {
        $key = "{$videoHash}/metadata.json";
        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => json_encode($metadata),
                'ACL' => 'public-read',
                'ContentType' => 'application/json',
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('S3 Metadata Save Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Copy an object within the same bucket
     */
    public function copyObject(string $sourceKey, string $destinationKey): bool
    {
        try {
            $this->s3Client->copyObject([
                'Bucket' => $this->bucket,
                'CopySource' => $this->bucket . '/' . $sourceKey,
                'Key' => $destinationKey,
                'ACL' => 'public-read',
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('S3 Copy Error: ' . $e->getMessage());
            return false;
        }
    }
}
