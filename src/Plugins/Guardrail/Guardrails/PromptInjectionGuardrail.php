<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Guardrails;

use JayI\Cortex\Plugins\Guardrail\Data\ContentType;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailContext;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;

/**
 * Detect potential prompt injection attacks.
 */
class PromptInjectionGuardrail extends AbstractGuardrail
{
    /** @var array<int, string> */
    protected array $suspiciousPatterns = [
        // Instruction overrides
        '/ignore\s+(all\s+)?(previous|prior|above|earlier)\s+(instructions?|prompts?|rules?)/i',
        '/disregard\s+(all\s+)?(previous|prior|above|earlier)\s+(instructions?|prompts?|rules?)/i',
        '/forget\s+(all\s+)?(previous|prior|above|earlier)\s+(instructions?|prompts?|rules?)/i',

        // Role manipulation
        '/you\s+are\s+(now|no\s+longer)\s+a/i',
        '/act\s+as\s+if\s+you\s+(are|were)/i',
        '/pretend\s+(to\s+be|you\s+are)/i',
        '/assume\s+the\s+role\s+of/i',

        // System prompt extraction
        '/what\s+(are|is)\s+your\s+(instructions?|rules?|system\s+prompt)/i',
        '/show\s+me\s+your\s+(instructions?|rules?|system\s+prompt)/i',
        '/reveal\s+your\s+(instructions?|rules?|system\s+prompt)/i',
        '/print\s+your\s+(instructions?|rules?|system\s+prompt)/i',

        // Jailbreak attempts
        '/\bDAN\b.*\bdo\s+anything\s+now\b/i',
        '/developer\s+mode/i',
        '/\bjailbreak\b/i',

        // Delimiter exploitation
        '/```\s*system/i',
        '/<\s*system\s*>/i',
        '/\[\s*SYSTEM\s*\]/i',
    ];

    /** @var array<int, string> */
    protected array $suspiciousKeywords = [
        'ignore instructions',
        'bypass restrictions',
        'override safety',
        'disable filters',
        'unrestricted mode',
    ];

    protected float $threshold = 0.5;

    public function __construct()
    {
        $this->contentTypes = [ContentType::Input];
        $this->priority = 100; // High priority
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'prompt-injection';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Prompt Injection Detection';
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(GuardrailContext $context): GuardrailResult
    {
        $content = $context->content;
        $score = 0.0;
        $detectedPatterns = [];
        $detectedKeywords = [];

        // Check patterns
        foreach ($this->suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $score += 0.3;
                $detectedPatterns[] = $matches[0];
            }
        }

        // Check keywords
        $lowerContent = strtolower($content);
        foreach ($this->suspiciousKeywords as $keyword) {
            if (str_contains($lowerContent, $keyword)) {
                $score += 0.2;
                $detectedKeywords[] = $keyword;
            }
        }

        // Additional heuristics
        $score += $this->checkStructuralIndicators($content);

        // Normalize score
        $score = min(1.0, $score);

        if ($score >= $this->threshold) {
            return GuardrailResult::block(
                guardrailId: $this->id(),
                reason: 'Potential prompt injection detected',
                category: 'injection',
                confidence: $score,
                metadata: [
                    'detected_patterns' => $detectedPatterns,
                    'detected_keywords' => $detectedKeywords,
                    'score' => $score,
                ],
            );
        }

        return GuardrailResult::pass($this->id());
    }

    /**
     * Check for structural indicators of injection.
     */
    protected function checkStructuralIndicators(string $content): float
    {
        $score = 0.0;

        // Unusual amount of special characters
        $specialCharRatio = preg_match_all('/[<>\[\]{}|\\\\`]/', $content) / max(1, strlen($content));
        if ($specialCharRatio > 0.05) {
            $score += 0.1;
        }

        // Multiple role-switching indicators
        if (preg_match_all('/\b(user|assistant|system|human|ai)\s*:/i', $content) > 2) {
            $score += 0.2;
        }

        // Base64-encoded content
        if (preg_match('/[A-Za-z0-9+\/]{50,}={0,2}/', $content)) {
            $score += 0.1;
        }

        return $score;
    }

    /**
     * Set the detection threshold.
     */
    public function setThreshold(float $threshold): self
    {
        $this->threshold = max(0.0, min(1.0, $threshold));

        return $this;
    }

    /**
     * Add suspicious patterns.
     *
     * @param  array<int, string>  $patterns
     */
    public function addPatterns(array $patterns): self
    {
        $this->suspiciousPatterns = array_merge($this->suspiciousPatterns, $patterns);

        return $this;
    }

    /**
     * Add suspicious keywords.
     *
     * @param  array<int, string>  $keywords
     */
    public function addKeywords(array $keywords): self
    {
        $this->suspiciousKeywords = array_merge($this->suspiciousKeywords, $keywords);

        return $this;
    }
}
