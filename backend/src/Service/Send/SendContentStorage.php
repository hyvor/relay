<?php

namespace App\Service\Send;

use App\Service\Send\Dto\SendContent;
use League\Flysystem\Filesystem;

class SendContentStorage
{
    public function __construct(
        private Filesystem $filesystem,
    ) {
    }

    public function store(string $uuid, SendContent $content): void
    {
        $this->filesystem->write($this->getRawPath($uuid), $content->raw);
        $this->filesystem->write($this->getJsonPath($uuid), $this->encodeJson($content));
    }

    public function getRaw(string $uuid): ?string
    {
        if (!$this->filesystem->fileExists($this->getRawPath($uuid))) {
            return null;
        }

        return $this->filesystem->read($this->getRawPath($uuid));
    }

    public function get(string $uuid): ?SendContent
    {
        if (!$this->filesystem->fileExists($this->getJsonPath($uuid))) {
            return null;
        }

        /** @var array{body_html: ?string, body_text: ?string, headers: array<string, string>} $data */
        $data = json_decode($this->filesystem->read($this->getJsonPath($uuid)), true);

        return new SendContent(
            raw: $this->getRaw($uuid) ?? '',
            bodyHtml: $data['body_html'],
            bodyText: $data['body_text'],
            headers: $data['headers'],
        );
    }

    public function delete(string $uuid): void
    {
        $this->filesystem->delete($this->getRawPath($uuid));
        $this->filesystem->delete($this->getJsonPath($uuid));
    }

    private function encodeJson(SendContent $content): string
    {
        $json = json_encode([
            'body_html' => $content->bodyHtml,
            'body_text' => $content->bodyText,
            'headers' => $content->headers,
        ]);
        assert(is_string($json));

        return $json;
    }

    private function getRawPath(string $uuid): string
    {
        return 'sends/' . $uuid . '.eml';
    }

    private function getJsonPath(string $uuid): string
    {
        return 'sends/' . $uuid . '.json';
    }
}
