<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Contracts;

use JayI\Cortex\Plugins\Guardrail\Data\GuardrailContext;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;

interface GuardrailPipelineContract
{
    /**
     * Add a guardrail to the pipeline.
     */
    public function add(GuardrailContract $guardrail): self;

    /**
     * Remove a guardrail from the pipeline.
     */
    public function remove(string $guardrailId): self;

    /**
     * Run all applicable guardrails on the content.
     *
     * @return array<int, GuardrailResult>
     */
    public function evaluate(GuardrailContext $context): array;

    /**
     * Check if content passes all guardrails.
     */
    public function passes(GuardrailContext $context): bool;

    /**
     * Get the first failing guardrail result.
     */
    public function firstFailure(GuardrailContext $context): ?GuardrailResult;

    /**
     * Get all guardrails.
     *
     * @return array<string, GuardrailContract>
     */
    public function all(): array;
}
