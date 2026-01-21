<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool;

use Spatie\LaravelData\Data;

class ToolResult extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $success,
        public mixed $output,
        public ?string $error = null,
        public bool $shouldContinue = true,
        public array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function success(mixed $output, array $metadata = []): static
    {
        return new static(
            success: true,
            output: $output,
            error: null,
            shouldContinue: true,
            metadata: $metadata,
        );
    }

    /**
     * Create an error result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function error(string $error, array $metadata = []): static
    {
        return new static(
            success: false,
            output: null,
            error: $error,
            shouldContinue: true,
            metadata: $metadata,
        );
    }

    /**
     * Create a result that signals the agent loop to stop.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function stop(mixed $output, array $metadata = []): static
    {
        return new static(
            success: true,
            output: $output,
            error: null,
            shouldContinue: false,
            metadata: $metadata,
        );
    }

    /**
     * Check if this result should stop the agent loop.
     */
    public function shouldStop(): bool
    {
        return ! $this->shouldContinue;
    }

    /**
     * Convert the output to a string for LLM consumption.
     */
    public function toContentString(): string
    {
        if ($this->error !== null) {
            return "Error: {$this->error}";
        }

        if (is_string($this->output)) {
            return $this->output;
        }

        if (is_array($this->output) || is_object($this->output)) {
            return json_encode($this->output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return (string) $this->output;
    }

    /**
     * Add metadata to the result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return new static(
            success: $this->success,
            output: $this->output,
            error: $this->error,
            shouldContinue: $this->shouldContinue,
            metadata: array_merge($this->metadata, $metadata),
        );
    }
}
