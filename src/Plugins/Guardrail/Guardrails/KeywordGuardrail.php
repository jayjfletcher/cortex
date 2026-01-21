<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Guardrails;

use JayI\Cortex\Plugins\Guardrail\Data\ContentType;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailContext;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;

/**
 * Block content containing specific keywords or patterns.
 */
class KeywordGuardrail extends AbstractGuardrail
{
    /**
     * @param  array<int, string>  $blockedKeywords  Keywords to block
     * @param  array<int, string>  $blockedPatterns  Regex patterns to block
     */
    public function __construct(
        protected array $blockedKeywords = [],
        protected array $blockedPatterns = [],
        protected bool $caseSensitive = false,
        protected string $category = 'keyword',
    ) {
        $this->contentTypes = [ContentType::Input, ContentType::Output];
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'keyword';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Keyword Filter';
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(GuardrailContext $context): GuardrailResult
    {
        $content = $this->caseSensitive ? $context->content : strtolower($context->content);

        // Check keywords
        foreach ($this->blockedKeywords as $keyword) {
            $searchKeyword = $this->caseSensitive ? $keyword : strtolower($keyword);

            if (str_contains($content, $searchKeyword)) {
                return GuardrailResult::block(
                    guardrailId: $this->id(),
                    reason: "Content contains blocked keyword: {$keyword}",
                    category: $this->category,
                    metadata: ['matched_keyword' => $keyword],
                );
            }
        }

        // Check patterns
        foreach ($this->blockedPatterns as $pattern) {
            $flags = $this->caseSensitive ? '' : 'i';
            $fullPattern = "/{$pattern}/{$flags}";

            if (preg_match($fullPattern, $context->content, $matches)) {
                return GuardrailResult::block(
                    guardrailId: $this->id(),
                    reason: 'Content matches blocked pattern',
                    category: $this->category,
                    metadata: [
                        'matched_pattern' => $pattern,
                        'matched_content' => $matches[0],
                    ],
                );
            }
        }

        return GuardrailResult::pass($this->id());
    }

    /**
     * Add blocked keywords.
     *
     * @param  array<int, string>  $keywords
     */
    public function addBlockedKeywords(array $keywords): self
    {
        $this->blockedKeywords = array_merge($this->blockedKeywords, $keywords);

        return $this;
    }

    /**
     * Add blocked patterns.
     *
     * @param  array<int, string>  $patterns
     */
    public function addBlockedPatterns(array $patterns): self
    {
        $this->blockedPatterns = array_merge($this->blockedPatterns, $patterns);

        return $this;
    }
}
