<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use JayI\Cortex\Plugins\Provider\Model;
use Spatie\LaravelData\Data;

class Usage extends Data
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public ?int $cacheReadTokens = null,
        public ?int $cacheWriteTokens = null,
    ) {}

    /**
     * Create a zero usage instance.
     */
    public static function zero(): static
    {
        return new static(0, 0);
    }

    /**
     * Get total token count.
     */
    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Estimate cost based on model pricing.
     */
    public function estimateCost(Model $model): ?float
    {
        return $model->estimateCost($this->inputTokens, $this->outputTokens);
    }

    /**
     * Add usage from another instance.
     */
    public function add(Usage $other): static
    {
        return new static(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            cacheReadTokens: ($this->cacheReadTokens ?? 0) + ($other->cacheReadTokens ?? 0) ?: null,
            cacheWriteTokens: ($this->cacheWriteTokens ?? 0) + ($other->cacheWriteTokens ?? 0) ?: null,
        );
    }
}
