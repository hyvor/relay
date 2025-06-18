<?php

namespace App\Service\Management\Health;

abstract class HealthCheckAbstract
{

    /** @var array<mixed> */
    private array $data = [];

    /**
     * true if health check passes, false otherwise.
     * can call setData() to set additional data when failing
     */
    abstract public function check(): bool;

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

}