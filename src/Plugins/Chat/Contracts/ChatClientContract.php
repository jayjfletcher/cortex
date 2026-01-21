<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Contracts;

use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\StreamedResponse;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

interface ChatClientContract
{
    /**
     * Send a synchronous chat request.
     */
    public function send(ChatRequest $request): ChatResponse;

    /**
     * Stream a chat response.
     */
    public function stream(ChatRequest $request): StreamedResponse;

    /**
     * Broadcast a streamed response to a channel.
     */
    public function broadcast(string $channel, ChatRequest $request): ChatResponse;

    /**
     * Get an SSE response.
     */
    public function sse(ChatRequest $request): SymfonyStreamedResponse;

    /**
     * Use a specific provider for this request.
     */
    public function using(string|ProviderContract $provider): static;
}
