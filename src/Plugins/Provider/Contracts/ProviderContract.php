<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider\Contracts;

use Illuminate\Support\Collection;
use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\StreamedResponse;
use JayI\Cortex\Plugins\Provider\Model;
use JayI\Cortex\Plugins\Provider\ProviderCapabilities;

interface ProviderContract
{
    /**
     * Get the provider ID.
     */
    public function id(): string;

    /**
     * Get the provider name.
     */
    public function name(): string;

    /**
     * Get provider capabilities.
     */
    public function capabilities(): ProviderCapabilities;

    /**
     * List available models.
     *
     * @return Collection<int, Model>
     */
    public function models(): Collection;

    /**
     * Get a specific model.
     */
    public function model(string $id): Model;

    /**
     * Count tokens for content.
     *
     * @param  string|array<int, mixed>|Message  $content
     */
    public function countTokens(string|array|Message $content, ?string $model = null): int;

    /**
     * Send a chat completion request.
     */
    public function chat(ChatRequest $request): ChatResponse;

    /**
     * Stream a chat completion request.
     */
    public function stream(ChatRequest $request): StreamedResponse;

    /**
     * Check if provider supports a specific feature.
     */
    public function supports(string $feature): bool;

    /**
     * Pass provider-specific options.
     *
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static;

    /**
     * Create a new instance with merged configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): static;

    /**
     * Get the default model ID.
     */
    public function defaultModel(): string;
}
