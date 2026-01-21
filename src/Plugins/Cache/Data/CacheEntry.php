<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Cache\Data;

use DateTimeImmutable;
use Spatie\LaravelData\Data;

/**
 * A cached response entry.
 */
class CacheEntry extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $response,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $expiresAt = null,
        public readonly int $hits = 0,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Check if the entry has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable;
    }

    /**
     * Create a new entry.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        string $key,
        mixed $response,
        ?int $ttlSeconds = null,
        array $metadata = [],
    ): self {
        $now = new DateTimeImmutable;
        $expiresAt = $ttlSeconds !== null
            ? $now->modify("+{$ttlSeconds} seconds")
            : null;

        return new self(
            key: $key,
            response: $response,
            createdAt: $now,
            expiresAt: $expiresAt,
            hits: 0,
            metadata: $metadata,
        );
    }

    /**
     * Record a cache hit.
     */
    public function recordHit(): self
    {
        return new self(
            key: $this->key,
            response: $this->response,
            createdAt: $this->createdAt,
            expiresAt: $this->expiresAt,
            hits: $this->hits + 1,
            metadata: $this->metadata,
        );
    }
}
