<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use Spatie\LaravelData\Data;

class AgentContext extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $conversationId = null,
        public ?string $runId = null,
        public ?string $tenantId = null,
        public ?MessageCollection $history = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a new context with a conversation ID.
     */
    public function withConversationId(string $conversationId): static
    {
        return new static(
            conversationId: $conversationId,
            runId: $this->runId,
            tenantId: $this->tenantId,
            history: $this->history,
            metadata: $this->metadata,
        );
    }

    /**
     * Create a new context with a run ID.
     */
    public function withRunId(string $runId): static
    {
        return new static(
            conversationId: $this->conversationId,
            runId: $runId,
            tenantId: $this->tenantId,
            history: $this->history,
            metadata: $this->metadata,
        );
    }

    /**
     * Create a new context with message history.
     */
    public function withHistory(MessageCollection $history): static
    {
        return new static(
            conversationId: $this->conversationId,
            runId: $this->runId,
            tenantId: $this->tenantId,
            history: $history,
            metadata: $this->metadata,
        );
    }

    /**
     * Create a new context with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return new static(
            conversationId: $this->conversationId,
            runId: $this->runId,
            tenantId: $this->tenantId,
            history: $this->history,
            metadata: array_merge($this->metadata, $metadata),
        );
    }
}
