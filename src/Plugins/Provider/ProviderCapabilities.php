<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider;

use Spatie\LaravelData\Data;

class ProviderCapabilities extends Data
{
    /**
     * @param  array<int, string>  $supportedMediaTypes
     * @param  array<string, mixed>  $custom
     */
    public function __construct(
        public bool $streaming = false,
        public bool $tools = false,
        public bool $parallelTools = false,
        public bool $vision = false,
        public bool $audio = false,
        public bool $documents = false,
        public bool $structuredOutput = false,
        public bool $jsonMode = false,
        public bool $promptCaching = false,
        public bool $systemMessages = true,
        public int $maxContextWindow = 4096,
        public int $maxOutputTokens = 4096,
        public array $supportedMediaTypes = [],
        public array $custom = [],
    ) {}

    /**
     * Check if a specific capability is supported.
     */
    public function supports(string $capability): bool
    {
        return match ($capability) {
            'streaming' => $this->streaming,
            'tools', 'tool_use' => $this->tools,
            'parallel_tools' => $this->parallelTools,
            'vision' => $this->vision,
            'audio' => $this->audio,
            'documents' => $this->documents,
            'structured_output' => $this->structuredOutput,
            'json_mode' => $this->jsonMode,
            'prompt_caching' => $this->promptCaching,
            'system_messages' => $this->systemMessages,
            default => isset($this->custom[$capability]) && $this->custom[$capability] === true,
        };
    }
}
