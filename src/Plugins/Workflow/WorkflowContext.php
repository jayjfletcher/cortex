<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use Spatie\LaravelData\Data;

class WorkflowContext extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $runId = null,
        public ?string $tenantId = null,
        public ?string $correlationId = null,
        public array $metadata = [],
    ) {}

    /**
     * Create context with a run ID.
     */
    public function withRunId(string $runId): static
    {
        return new static(
            runId: $runId,
            tenantId: $this->tenantId,
            correlationId: $this->correlationId,
            metadata: $this->metadata,
        );
    }

    /**
     * Create context with a tenant ID.
     */
    public function withTenantId(string $tenantId): static
    {
        return new static(
            runId: $this->runId,
            tenantId: $tenantId,
            correlationId: $this->correlationId,
            metadata: $this->metadata,
        );
    }

    /**
     * Create context with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return new static(
            runId: $this->runId,
            tenantId: $this->tenantId,
            correlationId: $this->correlationId,
            metadata: array_merge($this->metadata, $metadata),
        );
    }
}
