<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Events\Chat\AfterChatReceive;
use JayI\Cortex\Events\Chat\BeforeChatSend;
use JayI\Cortex\Events\Chat\ChatError;
use JayI\Cortex\Events\Chat\ChatStreamStarted;
use JayI\Cortex\Events\Concerns\DispatchesCortexEvents;
use JayI\Cortex\Plugins\Chat\Broadcasting\BroadcasterContract;
use JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

class ChatClient implements ChatClientContract
{
    use DispatchesCortexEvents;

    protected ?ProviderContract $provider = null;

    public function __construct(
        protected Container $container,
        protected ProviderRegistryContract $providerRegistry,
        protected PluginManagerContract $pluginManager,
    ) {}

    /**
     * Send a synchronous chat request.
     */
    public function send(ChatRequest $request): ChatResponse
    {
        $provider = $this->resolveProvider($request);

        // Apply hooks before sending
        $request = $this->pluginManager->applyHooks('chat.before_send', $request);

        $this->dispatchCortexEvent(new BeforeChatSend(
            request: $request,
        ));

        try {
            // Send to provider
            $response = $provider->chat($request);

            // Apply hooks after receiving
            $response = $this->pluginManager->applyHooks('chat.after_receive', $response, $request);

            $this->dispatchCortexEvent(new AfterChatReceive(
                request: $request,
                response: $response,
            ));

            return $response;
        } catch (\Throwable $e) {
            $this->dispatchCortexEvent(new ChatError(
                request: $request,
                exception: $e,
            ));

            throw $e;
        }
    }

    /**
     * Stream a chat response.
     */
    public function stream(ChatRequest $request): StreamedResponse
    {
        $provider = $this->resolveProvider($request);

        // Apply hooks before sending
        $request = $this->pluginManager->applyHooks('chat.before_send', $request);

        $this->dispatchCortexEvent(new BeforeChatSend(
            request: $request,
        ));

        $this->dispatchCortexEvent(new ChatStreamStarted(
            request: $request,
        ));

        return $provider->stream($request);
    }

    /**
     * Broadcast a streamed response to a channel.
     */
    public function broadcast(string $channel, ChatRequest $request): ChatResponse
    {
        $broadcaster = $this->container->make(BroadcasterContract::class);
        $stream = $this->stream($request);

        return $broadcaster->broadcast($channel, $stream);
    }

    /**
     * Get an SSE response.
     */
    public function sse(ChatRequest $request): SymfonyStreamedResponse
    {
        $stream = $this->stream($request);
        $config = $this->container->make('config')->get('cortex.chat.broadcasting.sse', []);
        $retry = $config['retry'] ?? 3000;

        return new SymfonyStreamedResponse(function () use ($stream, $retry) {
            echo "retry: {$retry}\n\n";

            foreach ($stream as $chunk) {
                $data = json_encode($chunk->toArray());
                echo "data: {$data}\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            echo "data: [DONE]\n\n";

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Use a specific provider for this request.
     */
    public function using(string|ProviderContract $provider): static
    {
        $clone = clone $this;

        if (is_string($provider)) {
            $clone->provider = $this->providerRegistry->get($provider);
        } else {
            $clone->provider = $provider;
        }

        return $clone;
    }

    /**
     * Resolve the provider to use for a request.
     */
    protected function resolveProvider(ChatRequest $request): ProviderContract
    {
        // Use explicitly set provider
        if ($this->provider !== null) {
            return $this->provider;
        }

        // Try to determine provider from model
        $model = $request->model;
        if ($model !== null) {
            // Check if model contains provider hint (e.g., "bedrock:claude-3")
            if (str_contains($model, ':')) {
                [$providerId] = explode(':', $model, 2);
                if ($this->providerRegistry->has($providerId)) {
                    return $this->providerRegistry->get($providerId);
                }
            }
        }

        // Use default provider
        return $this->providerRegistry->default();
    }
}
