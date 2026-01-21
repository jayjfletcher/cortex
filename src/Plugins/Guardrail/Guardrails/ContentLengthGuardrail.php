<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Guardrails;

use JayI\Cortex\Plugins\Guardrail\Data\ContentType;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailContext;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;

/**
 * Enforce content length limits.
 */
class ContentLengthGuardrail extends AbstractGuardrail
{
    public function __construct(
        protected ?int $minLength = null,
        protected ?int $maxLength = null,
        protected bool $countTokens = false, // If true, uses approximate token count
    ) {
        $this->contentTypes = [ContentType::Input];
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'content-length';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Content Length';
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(GuardrailContext $context): GuardrailResult
    {
        $length = $this->countTokens
            ? $this->estimateTokens($context->content)
            : mb_strlen($context->content);

        if ($this->minLength !== null && $length < $this->minLength) {
            return GuardrailResult::block(
                guardrailId: $this->id(),
                reason: "Content too short: {$length} < {$this->minLength}",
                category: 'length',
                metadata: [
                    'length' => $length,
                    'min_length' => $this->minLength,
                    'unit' => $this->countTokens ? 'tokens' : 'characters',
                ],
            );
        }

        if ($this->maxLength !== null && $length > $this->maxLength) {
            return GuardrailResult::block(
                guardrailId: $this->id(),
                reason: "Content too long: {$length} > {$this->maxLength}",
                category: 'length',
                metadata: [
                    'length' => $length,
                    'max_length' => $this->maxLength,
                    'unit' => $this->countTokens ? 'tokens' : 'characters',
                ],
            );
        }

        return GuardrailResult::pass($this->id());
    }

    /**
     * Estimate token count (rough approximation: 4 chars per token).
     */
    protected function estimateTokens(string $content): int
    {
        return (int) ceil(mb_strlen($content) / 4);
    }
}
