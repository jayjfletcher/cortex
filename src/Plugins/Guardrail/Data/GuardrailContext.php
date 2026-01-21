<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Data;

use Spatie\LaravelData\Data;

/**
 * Context for guardrail evaluation.
 */
class GuardrailContext extends Data
{
    public function __construct(
        public readonly string $content,
        public readonly ContentType $contentType = ContentType::Input,
        public readonly ?string $userId = null,
        public readonly ?string $sessionId = null,
        /** @var array<string, mixed> */
        public readonly array $conversationHistory = [],
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create an input context.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function input(
        string $content,
        ?string $userId = null,
        ?string $sessionId = null,
        array $metadata = [],
    ): self {
        return new self(
            content: $content,
            contentType: ContentType::Input,
            userId: $userId,
            sessionId: $sessionId,
            metadata: $metadata,
        );
    }

    /**
     * Create an output context.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function output(
        string $content,
        ?string $userId = null,
        ?string $sessionId = null,
        array $metadata = [],
    ): self {
        return new self(
            content: $content,
            contentType: ContentType::Output,
            userId: $userId,
            sessionId: $sessionId,
            metadata: $metadata,
        );
    }
}
