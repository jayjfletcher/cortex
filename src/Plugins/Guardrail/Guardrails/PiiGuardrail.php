<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Guardrails;

use JayI\Cortex\Plugins\Guardrail\Data\ContentType;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailContext;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;

/**
 * Detect and block personally identifiable information (PII).
 */
class PiiGuardrail extends AbstractGuardrail
{
    /** @var array<string, string> */
    protected array $patterns = [
        'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
        'phone_us' => '/(\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/',
        'ssn' => '/\b\d{3}[-.\s]?\d{2}[-.\s]?\d{4}\b/',
        'credit_card' => '/\b(?:\d{4}[-.\s]?){3}\d{4}\b/',
        'ip_address' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
    ];

    /**
     * @param  array<int, string>  $enabledTypes  PII types to detect
     * @param  bool  $blockOnDetection  Whether to block or just report
     */
    public function __construct(
        protected array $enabledTypes = ['email', 'phone_us', 'ssn', 'credit_card'],
        protected bool $blockOnDetection = true,
    ) {
        $this->contentTypes = [ContentType::Input, ContentType::Output];
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'pii';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'PII Detection';
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(GuardrailContext $context): GuardrailResult
    {
        $detectedTypes = [];
        $matches = [];

        foreach ($this->enabledTypes as $type) {
            if (! isset($this->patterns[$type])) {
                continue;
            }

            $pattern = $this->patterns[$type];

            if (preg_match_all($pattern, $context->content, $typeMatches)) {
                $detectedTypes[] = $type;
                $matches[$type] = $typeMatches[0];
            }
        }

        if (! empty($detectedTypes)) {
            if ($this->blockOnDetection) {
                return GuardrailResult::block(
                    guardrailId: $this->id(),
                    reason: 'PII detected: '.implode(', ', $detectedTypes),
                    category: 'pii',
                    metadata: [
                        'detected_types' => $detectedTypes,
                        'match_count' => array_sum(array_map('count', $matches)),
                    ],
                );
            }

            // Return pass with metadata for logging
            return new GuardrailResult(
                passed: true,
                guardrailId: $this->id(),
                reason: 'PII detected but not blocking',
                category: 'pii',
                metadata: [
                    'detected_types' => $detectedTypes,
                    'match_count' => array_sum(array_map('count', $matches)),
                ],
            );
        }

        return GuardrailResult::pass($this->id());
    }

    /**
     * Add a custom PII pattern.
     */
    public function addPattern(string $type, string $pattern): self
    {
        $this->patterns[$type] = $pattern;

        return $this;
    }

    /**
     * Enable detection for specific PII types.
     *
     * @param  array<int, string>  $types
     */
    public function enableTypes(array $types): self
    {
        $this->enabledTypes = $types;

        return $this;
    }
}
