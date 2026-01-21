<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Data;

use DateTimeImmutable;
use Spatie\LaravelData\Data;

/**
 * Record of a single API usage event.
 */
class UsageRecord extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly float $cost,
        public readonly DateTimeImmutable $timestamp,
        public readonly ?string $requestId = null,
        public readonly ?string $userId = null,
        public readonly ?string $sessionId = null,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Get total tokens (input + output).
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Create a new record.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $cost,
        ?string $requestId = null,
        ?string $userId = null,
        ?string $sessionId = null,
        array $metadata = [],
    ): self {
        return new self(
            id: uniqid('usage_', true),
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            cost: $cost,
            timestamp: new DateTimeImmutable,
            requestId: $requestId,
            userId: $userId,
            sessionId: $sessionId,
            metadata: $metadata,
        );
    }
}
