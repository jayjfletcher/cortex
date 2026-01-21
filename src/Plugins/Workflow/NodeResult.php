<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use Spatie\LaravelData\Data;

class NodeResult extends Data
{
    /**
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $output,
        public ?string $nextNode = null,
        public bool $shouldPause = false,
        public ?string $pauseReason = null,
        public bool $success = true,
        public ?string $error = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $output
     */
    public static function success(array $output, ?string $nextNode = null): static
    {
        return new static(
            output: $output,
            nextNode: $nextNode,
        );
    }

    /**
     * Create a result that pauses the workflow.
     *
     * @param  array<string, mixed>  $output
     */
    public static function pause(string $reason, array $output = []): static
    {
        return new static(
            output: $output,
            shouldPause: true,
            pauseReason: $reason,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failure(string $error): static
    {
        return new static(
            output: [],
            success: false,
            error: $error,
        );
    }

    /**
     * Create a result that transitions to a specific node.
     *
     * @param  array<string, mixed>  $output
     */
    public static function goto(string $nodeId, array $output = []): static
    {
        return new static(
            output: $output,
            nextNode: $nodeId,
        );
    }
}
