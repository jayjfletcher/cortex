<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Schema\Schema;

class ChatRequestBuilder
{
    protected MessageCollection $messages;

    protected ?string $systemPrompt = null;

    protected ?string $model = null;

    protected ChatOptions $options;

    protected ?ToolCollection $tools = null;

    protected ?Schema $responseSchema = null;

    /**
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    public function __construct()
    {
        $this->messages = MessageCollection::make();
        $this->options = new ChatOptions();
    }

    /**
     * Set the system prompt.
     */
    public function system(string $prompt): static
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    /**
     * Add a message.
     */
    public function message(string|Message $message): static
    {
        if (is_string($message)) {
            $this->messages->user($message);
        } else {
            $this->messages->add($message);
        }

        return $this;
    }

    /**
     * Set all messages.
     *
     * @param  array<int, Message>|MessageCollection  $messages
     */
    public function messages(array|MessageCollection $messages): static
    {
        if (is_array($messages)) {
            $this->messages = MessageCollection::make($messages);
        } else {
            $this->messages = $messages;
        }

        return $this;
    }

    /**
     * Set the model.
     */
    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set chat options.
     *
     * @param  ChatOptions|array<string, mixed>  $options
     */
    public function options(ChatOptions|array $options): static
    {
        if (is_array($options)) {
            $this->options = ChatOptions::from($options);
        } else {
            $this->options = $options;
        }

        return $this;
    }

    /**
     * Set the temperature.
     */
    public function temperature(float $temperature): static
    {
        $this->options = new ChatOptions(
            temperature: $temperature,
            maxTokens: $this->options->maxTokens,
            topP: $this->options->topP,
            topK: $this->options->topK,
            stopSequences: $this->options->stopSequences,
            toolChoice: $this->options->toolChoice,
            providerOptions: $this->options->providerOptions,
        );

        return $this;
    }

    /**
     * Set the max tokens.
     */
    public function maxTokens(int $maxTokens): static
    {
        $this->options = new ChatOptions(
            temperature: $this->options->temperature,
            maxTokens: $maxTokens,
            topP: $this->options->topP,
            topK: $this->options->topK,
            stopSequences: $this->options->stopSequences,
            toolChoice: $this->options->toolChoice,
            providerOptions: $this->options->providerOptions,
        );

        return $this;
    }

    /**
     * Set the tools.
     *
     * @param  ToolCollection|array<int, mixed>  $tools
     */
    public function tools(ToolCollection|array $tools): static
    {
        if (is_array($tools)) {
            $this->tools = ToolCollection::make($tools);
        } else {
            $this->tools = $tools;
        }

        return $this;
    }

    /**
     * Set the response schema.
     */
    public function responseSchema(Schema $schema): static
    {
        $this->responseSchema = $schema;

        return $this;
    }

    /**
     * Set metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Build the request.
     */
    public function build(): ChatRequest
    {
        // Add system message if set and not already in messages
        if ($this->systemPrompt !== null) {
            $hasSystem = false;
            foreach ($this->messages as $message) {
                if ($message->role === MessageRole::System) {
                    $hasSystem = true;
                    break;
                }
            }

            if (! $hasSystem) {
                $this->messages->prepend(Message::system($this->systemPrompt));
            }
        }

        return new ChatRequest(
            messages: $this->messages,
            systemPrompt: $this->systemPrompt,
            model: $this->model,
            options: $this->options,
            tools: $this->tools,
            responseSchema: $this->responseSchema,
            metadata: $this->metadata,
        );
    }

    /**
     * Build and send the request.
     */
    public function send(?Container $container = null): ChatResponse
    {
        $container ??= app();
        $client = $container->make(ChatClientContract::class);

        return $client->send($this->build());
    }

    /**
     * Build and stream the request.
     */
    public function stream(?Container $container = null): StreamedResponse
    {
        $container ??= app();
        $client = $container->make(ChatClientContract::class);

        return $client->stream($this->build());
    }
}
