<?php

namespace App\Service\Storage;

use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

class FilesystemFactory
{
    /** @param 's3'|'file'|'memory' $adapterType */
    public static function create(
        string $adapterType,
        S3Client $s3Client,
        ?string $bucket,
    ): Filesystem {
        if ($adapterType === 's3') {
            $adapter = new AsyncAwsS3Adapter($s3Client, $bucket ?? '');
        } elseif ($adapterType === 'file') {
            $adapter = new LocalFilesystemAdapter('/app/media');
        } else {
            $adapter = new InMemoryFilesystemAdapter();
        }

        return new Filesystem($adapter);
    }
}
