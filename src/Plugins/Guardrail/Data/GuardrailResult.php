<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Data;

use Spatie\LaravelData\Data;

/**
 * Result of a guardrail check.
 */
class GuardrailResult extends Data
{
    public function __construct(
        public readonly bool $passed,
        public readonly string $guardrailId,
        public readonly ?string $reason = null,
        public readonly ?string $category = null,
        public readonly float $confidence = 1.0,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a passing result.
     */
    public static function pass(string $guardrailId): self
    {
        return new self(
            passed: true,
            guardrailId: $guardrailId,
        );
    }

    /**
     * Create a blocking result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function block(
        string $guardrailId,
        string $reason,
        ?string $category = null,
        float $confidence = 1.0,
        array $metadata = [],
    ): self {
        return new self(
            passed: false,
            guardrailId: $guardrailId,
            reason: $reason,
            category: $category,
            confidence: $confidence,
            metadata: $metadata,
        );
    }
}
