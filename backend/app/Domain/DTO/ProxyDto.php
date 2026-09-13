<?php

declare(strict_types=1);

namespace App\Domain\DTO;

class ProxyDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $protocol,
        public readonly string $host,
        public readonly int $port,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
    ) {}

    /**
     * Get connection string for HTTP client (e.g. 'http://user:pass@host:port').
     */
    public function toHttpOption(): string
    {
        $auth = '';
        if ($this->username !== null && $this->username !== '') {
            $auth = $this->password !== null && $this->password !== ''
                ? "{$this->username}:{$this->password}@"
                : "{$this->username}@";
        }

        return "{$this->protocol}://{$auth}{$this->host}:{$this->port}";
    }

    /**
     * Masked string for logging (hides password).
     */
    public function toMaskedString(): string
    {
        $auth = '';
        if ($this->username !== null && $this->username !== '') {
            $auth = "{$this->username}:***@";
        }

        return "{$this->protocol}://{$auth}{$this->host}:{$this->port}";
    }
}
