<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool;

use JayI\Cortex\Plugins\Chat\Messages\Message;
use Spatie\LaravelData\Data;

class ToolContext extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $conversationId = null,
        public ?string $agentId = null,
        public ?string $tenantId = null,
        public ?Message $triggeringMessage = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a context with a conversation ID.
     */
    public static function forConversation(string $conversationId): static
    {
        return new static(conversationId: $conversationId);
    }

    /**
     * Create a context for an agent.
     */
    public static function forAgent(string $agentId, ?string $conversationId = null): static
    {
        return new static(
            conversationId: $conversationId,
            agentId: $agentId,
        );
    }

    /**
     * Create a context with a tenant.
     */
    public static function forTenant(string $tenantId): static
    {
        return new static(tenantId: $tenantId);
    }

    /**
     * Add metadata to the context.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        return new static(
            conversationId: $this->conversationId,
            agentId: $this->agentId,
            tenantId: $this->tenantId,
            triggeringMessage: $this->triggeringMessage,
            metadata: array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Set the triggering message.
     */
    public function withMessage(Message $message): static
    {
        return new static(
            conversationId: $this->conversationId,
            agentId: $this->agentId,
            tenantId: $this->tenantId,
            triggeringMessage: $message,
            metadata: $this->metadata,
        );
    }

    /**
     * Get a metadata value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
