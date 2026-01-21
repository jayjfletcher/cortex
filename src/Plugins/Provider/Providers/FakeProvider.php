<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider\Providers;

use Closure;
use Generator;
use Illuminate\Support\Collection;
use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\StreamChunk;
use JayI\Cortex\Plugins\Chat\StreamedResponse;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\TextContent;
use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\Model;
use JayI\Cortex\Plugins\Provider\ProviderCapabilities;
use PHPUnit\Framework\Assert;
use RuntimeException;

class FakeProvider implements ProviderContract
{
    /**
     * @var array<int, ChatResponse|StreamedResponse|Closure>
     */
    protected array $responses = [];

    protected int $responseIndex = 0;

    /**
     * @var array<int, ChatRequest>
     */
    protected array $recordedRequests = [];

    protected ?Closure $responseFactory = null;

    /**
     * @var array<string, mixed>
     */
    protected array $providerOptions = [];

    public function __construct(
        protected string $providerId = 'fake',
    ) {}

    /**
     * Create a fake provider with queued responses.
     *
     * @param  array<int, ChatResponse|StreamedResponse|Closure|string>  $responses
     */
    public static function fake(array $responses = []): static
    {
        $fake = new static();

        foreach ($responses as $response) {
            $fake->addResponse($response);
        }

        return $fake;
    }

    /**
     * Create a fake that always returns a simple text response.
     */
    public static function text(string $content): static
    {
        $fake = new static();
        $fake->respondWith(fn () => ChatResponse::fromText($content));

        return $fake;
    }

    /**
     * Create a fake that always requests tool calls.
     *
     * @param  array<int, array{name: string, input: array<string, mixed>}>  $toolCalls
     */
    public static function withToolCalls(array $toolCalls): static
    {
        $fake = new static();
        $fake->respondWith(function () use ($toolCalls) {
            $content = [];
            foreach ($toolCalls as $index => $call) {
                $content[] = new ToolUseContent(
                    id: 'toolu_'.uniqid(),
                    name: $call['name'],
                    input: $call['input'] ?? [],
                );
            }

            return new ChatResponse(
                message: new Message(
                    role: MessageRole::Assistant,
                    content: $content,
                ),
                usage: Usage::zero(),
                stopReason: StopReason::ToolUse,
            );
        });

        return $fake;
    }

    /**
     * Queue a response to be returned.
     *
     * @param  ChatResponse|StreamedResponse|Closure|string  $response
     */
    public function addResponse(ChatResponse|StreamedResponse|Closure|string $response): static
    {
        if (is_string($response)) {
            $response = ChatResponse::fromText($response);
        }

        $this->responses[] = $response;

        return $this;
    }

    /**
     * Queue multiple responses.
     *
     * @param  array<int, ChatResponse|StreamedResponse|Closure|string>  $responses
     */
    public function addResponses(array $responses): static
    {
        foreach ($responses as $response) {
            $this->addResponse($response);
        }

        return $this;
    }

    /**
     * Set a response factory for dynamic responses.
     */
    public function respondWith(Closure $factory): static
    {
        $this->responseFactory = $factory;

        return $this;
    }

    /**
     * Get all recorded requests.
     *
     * @return array<int, ChatRequest>
     */
    public function recordedRequests(): array
    {
        return $this->recordedRequests;
    }

    /**
     * Assert a request was made.
     */
    public function assertSent(Closure $callback): void
    {
        $matching = array_filter(
            $this->recordedRequests,
            fn (ChatRequest $request) => $callback($request) === true
        );

        Assert::assertNotEmpty(
            $matching,
            'No request matching the given callback was sent.'
        );
    }

    /**
     * Assert request count.
     */
    public function assertSentCount(int $count): void
    {
        Assert::assertCount(
            $count,
            $this->recordedRequests,
            "Expected {$count} requests, but ".count($this->recordedRequests).' were sent.'
        );
    }

    /**
     * Assert no requests were made.
     */
    public function assertNothingSent(): void
    {
        Assert::assertEmpty(
            $this->recordedRequests,
            'Requests were sent when none were expected.'
        );
    }

    /**
     * Get the provider ID.
     */
    public function id(): string
    {
        return $this->providerId;
    }

    /**
     * Get the provider name.
     */
    public function name(): string
    {
        return 'Fake Provider';
    }

    /**
     * Get provider capabilities.
     */
    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            streaming: true,
            tools: true,
            parallelTools: true,
            vision: true,
            audio: false,
            documents: true,
            structuredOutput: true,
            jsonMode: true,
            systemMessages: true,
            maxContextWindow: 200000,
            maxOutputTokens: 8192,
        );
    }

    /**
     * List available models.
     *
     * @return Collection<int, Model>
     */
    public function models(): Collection
    {
        return new Collection([
            new Model(
                id: 'fake-model',
                name: 'Fake Model',
                provider: $this->providerId,
                contextWindow: 200000,
                maxOutputTokens: 8192,
            ),
        ]);
    }

    /**
     * Get a specific model.
     */
    public function model(string $id): Model
    {
        return new Model(
            id: $id,
            name: 'Fake Model: '.$id,
            provider: $this->providerId,
            contextWindow: 200000,
            maxOutputTokens: 8192,
        );
    }

    /**
     * Count tokens for content.
     *
     * @param  string|array<int, mixed>|Message  $content
     */
    public function countTokens(string|array|Message $content, ?string $model = null): int
    {
        $text = is_string($content)
            ? $content
            : ($content instanceof Message ? ($content->text() ?? '') : '');

        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Send a chat completion request.
     */
    public function chat(ChatRequest $request): ChatResponse
    {
        $this->recordedRequests[] = $request;

        return $this->getNextResponse($request);
    }

    /**
     * Stream a chat completion request.
     */
    public function stream(ChatRequest $request): StreamedResponse
    {
        $this->recordedRequests[] = $request;

        $response = $this->getNextResponse($request);

        // Convert ChatResponse to StreamedResponse
        return new StreamedResponse(function () use ($response): Generator {
            $text = $response->content();

            // Stream text in chunks
            $chunks = str_split($text, 10);
            foreach ($chunks as $index => $chunk) {
                yield $index => StreamChunk::textDelta($chunk, $index);
            }

            // Yield tool calls if any
            foreach ($response->toolCalls() as $index => $toolCall) {
                yield count($chunks) + $index => StreamChunk::toolUseStart($toolCall, count($chunks) + $index);
            }

            // Final chunk
            yield StreamChunk::messageComplete(
                $response->usage,
                $response->stopReason,
                count($chunks) + count($response->toolCalls())
            );
        });
    }

    /**
     * Check if provider supports a specific feature.
     */
    public function supports(string $feature): bool
    {
        return $this->capabilities()->supports($feature);
    }

    /**
     * Pass provider-specific options.
     *
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->providerOptions = array_merge($this->providerOptions, $options);

        return $clone;
    }

    /**
     * Create a new instance with merged configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): static
    {
        return clone $this;
    }

    /**
     * Get the default model ID.
     */
    public function defaultModel(): string
    {
        return 'fake-model';
    }

    /**
     * Get the next response from the queue.
     */
    protected function getNextResponse(ChatRequest $request): ChatResponse
    {
        // Use factory if set
        if ($this->responseFactory !== null) {
            $response = ($this->responseFactory)($request);
            if ($response instanceof ChatResponse) {
                return $response;
            }
            if (is_string($response)) {
                return ChatResponse::fromText($response);
            }
        }

        // Get from queue
        if (! isset($this->responses[$this->responseIndex])) {
            throw new RuntimeException('No more fake responses available. Add more responses or set a response factory.');
        }

        $response = $this->responses[$this->responseIndex];
        $this->responseIndex++;

        if ($response instanceof Closure) {
            $response = $response($request);
        }

        if ($response instanceof ChatResponse) {
            return $response;
        }

        if (is_string($response)) {
            return ChatResponse::fromText($response);
        }

        throw new RuntimeException('Invalid response type in fake provider queue.');
    }

    /**
     * Reset the provider state.
     */
    public function reset(): static
    {
        $this->responses = [];
        $this->responseIndex = 0;
        $this->recordedRequests = [];
        $this->responseFactory = null;

        return $this;
    }
}
