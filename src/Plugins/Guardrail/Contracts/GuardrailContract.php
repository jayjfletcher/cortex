<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Contracts;

use JayI\Cortex\Plugins\Guardrail\Data\ContentType;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailContext;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;

interface GuardrailContract
{
    /**
     * Get the guardrail identifier.
     */
    public function id(): string;

    /**
     * Get the guardrail name.
     */
    public function name(): string;

    /**
     * Get content types this guardrail applies to.
     *
     * @return array<int, ContentType>
     */
    public function appliesTo(): array;

    /**
     * Evaluate content against the guardrail.
     */
    public function evaluate(GuardrailContext $context): GuardrailResult;

    /**
     * Check if the guardrail is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the priority (higher = runs first).
     */
    public function priority(): int;
}
