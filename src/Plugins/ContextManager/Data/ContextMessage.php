<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\ContextManager\Data;

use DateTimeImmutable;
use Spatie\LaravelData\Data;

/**
 * A message in the context window.
 */
class ContextMessage extends Data
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly int $tokens,
        public readonly DateTimeImmutable $timestamp,
        public readonly float $importance = 0.5,
        public readonly bool $pinned = false,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a new context message.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        string $role,
        string $content,
        ?int $tokens = null,
        float $importance = 0.5,
        bool $pinned = false,
        array $metadata = [],
    ): self {
        return new self(
            role: $role,
            content: $content,
            tokens: $tokens ?? ContextWindow::estimateTokens($content),
            timestamp: new DateTimeImmutable,
            importance: max(0.0, min(1.0, $importance)),
            pinned: $pinned,
            metadata: $metadata,
        );
    }

    /**
     * Create a user message.
     */
    public static function user(string $content, float $importance = 0.5): self
    {
        return self::create('user', $content, importance: $importance);
    }

    /**
     * Create an assistant message.
     */
    public static function assistant(string $content, float $importance = 0.5): self
    {
        return self::create('assistant', $content, importance: $importance);
    }

    /**
     * Mark this message as pinned.
     */
    public function pin(): self
    {
        return new self(
            role: $this->role,
            content: $this->content,
            tokens: $this->tokens,
            timestamp: $this->timestamp,
            importance: $this->importance,
            pinned: true,
            metadata: $this->metadata,
        );
    }

    /**
     * Set importance score.
     */
    public function withImportance(float $importance): self
    {
        return new self(
            role: $this->role,
            content: $this->content,
            tokens: $this->tokens,
            timestamp: $this->timestamp,
            importance: max(0.0, min(1.0, $importance)),
            pinned: $this->pinned,
            metadata: $this->metadata,
        );
    }
}
