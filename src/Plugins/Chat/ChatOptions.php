<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use Spatie\LaravelData\Data;

class ChatOptions extends Data
{
    /**
     * @param  array<int, string>  $stopSequences
     * @param  array<string, mixed>  $providerOptions
     */
    public function __construct(
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?float $topP = null,
        public ?int $topK = null,
        public array $stopSequences = [],
        public ?string $toolChoice = null,
        public array $providerOptions = [],
    ) {}

    /**
     * Create default options.
     */
    public static function defaults(): static
    {
        return new static(
            temperature: 0.7,
            maxTokens: 4096,
        );
    }

    /**
     * Merge with another set of options.
     */
    public function merge(ChatOptions $other): static
    {
        return new static(
            temperature: $other->temperature ?? $this->temperature,
            maxTokens: $other->maxTokens ?? $this->maxTokens,
            topP: $other->topP ?? $this->topP,
            topK: $other->topK ?? $this->topK,
            stopSequences: array_merge($this->stopSequences, $other->stopSequences),
            toolChoice: $other->toolChoice ?? $this->toolChoice,
            providerOptions: array_merge($this->providerOptions, $other->providerOptions),
        );
    }
}
