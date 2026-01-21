<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use DateTimeImmutable;
use DateTimeInterface;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class WorkflowHistoryEntry extends Data
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $nodeId,
        public array $input,
        public array $output,
        public float $duration,
        #[WithCast(DateTimeInterfaceCast::class, format: ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP', 'Y-m-d H:i:s', DATE_ATOM], type: DateTimeImmutable::class)]
        public DateTimeInterface $executedAt,
        public bool $success = true,
        public ?string $error = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a successful history entry.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    public static function success(string $nodeId, array $input, array $output, float $duration): static
    {
        return new static(
            nodeId: $nodeId,
            input: $input,
            output: $output,
            duration: $duration,
            executedAt: new DateTimeImmutable,
            success: true,
        );
    }

    /**
     * Create a failed history entry.
     *
     * @param  array<string, mixed>  $input
     */
    public static function failure(string $nodeId, array $input, string $error, float $duration): static
    {
        return new static(
            nodeId: $nodeId,
            input: $input,
            output: [],
            duration: $duration,
            executedAt: new DateTimeImmutable,
            success: false,
            error: $error,
        );
    }
}
