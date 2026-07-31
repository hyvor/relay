<?php

namespace App\Service\Send;

use App\Service\Send\Dto\SendContent;
use App\Service\Send\Exception\SendContentStorageException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PhpMimeMailParser\Parser;

class SendContentStorage
{
    public function __construct(
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @throws SendContentStorageException
     */
    public function store(string $uuid, string $rawEmail): void
    {
        try {
            $this->filesystem->write($this->getRawPath($uuid), $rawEmail);
        } catch (FilesystemException $e) {
            throw new SendContentStorageException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @throws SendContentStorageException
     */
    public function getRaw(string $uuid): ?string
    {
        try {
            if (!$this->filesystem->fileExists($this->getRawPath($uuid))) {
                return null;
            }

            return $this->filesystem->read($this->getRawPath($uuid));
        } catch (FilesystemException $e) {
            throw new SendContentStorageException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @throws SendContentStorageException
     */
    public function get(string $uuid): ?SendContent
    {
        $raw = $this->getRaw($uuid);
        if ($raw === null) {
            return null;
        }

        try {
            $parser = new Parser();
            $parser->setText($raw);

            $bodyHtml = $parser->getMessageBody('html');
            $bodyText = $parser->getMessageBody('text');

            /** @var array<string, string> $headers */
            $headers = $parser->getHeaders();

            return new SendContent(
                raw: $raw,
                bodyHtml: is_string($bodyHtml) && trim($bodyHtml) !== '' ? trim($bodyHtml) : null,
                bodyText: is_string($bodyText) && trim($bodyText) !== '' ? trim($bodyText) : null,
                headers: $headers,
            );
        } catch (\Throwable $e) {
            throw new SendContentStorageException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @throws SendContentStorageException
     */
    public function delete(string $uuid): void
    {
        try {
            $this->filesystem->delete($this->getRawPath($uuid));
        } catch (FilesystemException $e) {
            throw new SendContentStorageException($e->getMessage(), previous: $e);
        }
    }

    private function getRawPath(string $uuid): string
    {
        return 'sends/' . $uuid . '.eml';
    }
}
