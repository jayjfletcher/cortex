<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Schema\Schema;
use Spatie\LaravelData\Data;

class ChatRequest extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public MessageCollection $messages,
        public ?string $systemPrompt = null,
        public ?string $model = null,
        public ChatOptions $options = new ChatOptions(),
        public ?ToolCollection $tools = null,
        public ?Schema $responseSchema = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a new request builder.
     */
    public static function make(): ChatRequestBuilder
    {
        return new ChatRequestBuilder();
    }

    /**
     * Create a simple request with a single message.
     */
    public static function simple(string $message, ?string $systemPrompt = null): static
    {
        $messages = MessageCollection::make();
        if ($systemPrompt !== null) {
            $messages->system($systemPrompt);
        }
        $messages->user($message);

        return new static(
            messages: $messages,
            systemPrompt: $systemPrompt,
        );
    }

    /**
     * Check if the request has tools.
     */
    public function hasTools(): bool
    {
        return $this->tools !== null && $this->tools->count() > 0;
    }

    /**
     * Check if the request has a response schema.
     */
    public function hasResponseSchema(): bool
    {
        return $this->responseSchema !== null;
    }

    /**
     * Get the system message from the messages collection.
     */
    public function getSystemMessage(): ?Message
    {
        foreach ($this->messages as $message) {
            if ($message->role === MessageRole::System) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Get the effective system prompt (from explicit systemPrompt or first system message).
     */
    public function getEffectiveSystemPrompt(): ?string
    {
        if ($this->systemPrompt !== null) {
            return $this->systemPrompt;
        }

        $systemMessage = $this->getSystemMessage();

        return $systemMessage?->text();
    }
}
