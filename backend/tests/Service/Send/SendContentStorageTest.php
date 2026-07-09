<?php

namespace App\Tests\Service\Send;

use App\Service\Send\Dto\SendContent;
use App\Service\Send\Exception\SendContentStorageException;
use App\Service\Send\SendContentStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendContentStorage::class)]
#[CoversClass(SendContentStorageException::class)]
#[CoversClass(SendContent::class)]
class SendContentStorageTest extends TestCase
{
    private SendContentStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new SendContentStorage(new Filesystem(new InMemoryFilesystemAdapter()));
    }

    private function content(): SendContent
    {
        return new SendContent(
            raw: 'raw-mime-content',
            bodyHtml: '<p>Hello</p>',
            bodyText: 'Hello',
            headers: ['X-Custom' => 'value'],
        );
    }

    public function test_store_writes_raw_and_json(): void
    {
        $this->storage->store('uuid-1', $this->content());

        $this->assertSame('raw-mime-content', $this->storage->getRaw('uuid-1'));

        $content = $this->storage->get('uuid-1');
        $this->assertNotNull($content);
        $this->assertSame('<p>Hello</p>', $content->bodyHtml);
        $this->assertSame('Hello', $content->bodyText);
        $this->assertSame(['X-Custom' => 'value'], $content->headers);
    }

    public function test_get_raw_returns_null_when_missing(): void
    {
        $this->assertNull($this->storage->getRaw('missing'));
    }

    public function test_get_raw_returns_content_when_exists(): void
    {
        $this->storage->store('uuid-1', $this->content());
        $this->assertSame('raw-mime-content', $this->storage->getRaw('uuid-1'));
    }

    public function test_get_returns_null_when_missing(): void
    {
        $this->assertNull($this->storage->get('missing'));
    }

    public function test_get_returns_content_when_exists(): void
    {
        $this->storage->store('uuid-1', $this->content());

        $content = $this->storage->get('uuid-1');
        $this->assertNotNull($content);
        $this->assertSame('raw-mime-content', $content->raw);
    }

    public function test_delete_removes_both_files(): void
    {
        $this->storage->store('uuid-1', $this->content());
        $this->storage->delete('uuid-1');

        $this->assertNull($this->storage->getRaw('uuid-1'));
        $this->assertNull($this->storage->get('uuid-1'));
    }

    public function test_store_throws_on_filesystem_error(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('write')->willThrowException(UnableToWriteFile::atLocation('path'));
        $storage = new SendContentStorage($filesystem);

        $this->expectException(SendContentStorageException::class);
        $storage->store('uuid-1', $this->content());
    }

    public function test_get_throws_on_filesystem_error(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('fileExists')->willThrowException(UnableToReadFile::fromLocation('path'));
        $storage = new SendContentStorage($filesystem);

        $this->expectException(SendContentStorageException::class);
        $storage->get('uuid-1');
    }

    public function test_delete_throws_on_filesystem_error(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('delete')->willThrowException(UnableToDeleteFile::atLocation('path'));
        $storage = new SendContentStorage($filesystem);

        $this->expectException(SendContentStorageException::class);
        $storage->delete('uuid-1');
    }
}
