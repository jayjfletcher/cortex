<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider\Providers;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use Generator;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use JayI\Cortex\Events\Concerns\DispatchesCortexEvents;
use JayI\Cortex\Events\Provider\AfterProviderResponse;
use JayI\Cortex\Events\Provider\BeforeProviderRequest;
use JayI\Cortex\Events\Provider\ProviderError;
use JayI\Cortex\Events\Provider\ProviderRateLimited;
use JayI\Cortex\Exceptions\ProviderException;
use JayI\Cortex\Plugins\Chat\ChatOptions;
use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Chat\Messages\Content;
use JayI\Cortex\Plugins\Chat\Messages\DocumentContent;
use JayI\Cortex\Plugins\Chat\Messages\ImageContent;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\SourceType;
use JayI\Cortex\Plugins\Chat\Messages\TextContent;
use JayI\Cortex\Plugins\Chat\Messages\ToolResultContent;
use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\StreamChunk;
use JayI\Cortex\Plugins\Chat\StreamedResponse;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\Model;
use JayI\Cortex\Plugins\Provider\ProviderCapabilities;

class BedrockProvider implements ProviderContract
{
    use DispatchesCortexEvents;

    protected BedrockRuntimeClient $client;

    /**
     * @var array<string, mixed>
     */
    protected array $providerOptions = [];

    /**
     * @var Collection<string, Model>|null
     */
    protected ?Collection $cachedModels = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected string $providerId,
        protected array $config,
        protected Container $container,
    ) {
        $this->client = $this->createClient();
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
        return 'AWS Bedrock';
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
            promptCaching: true,
            systemMessages: true,
            maxContextWindow: 200000,
            maxOutputTokens: 8192,
            supportedMediaTypes: [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
            ],
        );
    }

    /**
     * List available models.
     *
     * @return Collection<int, Model>
     */
    public function models(): Collection
    {
        if ($this->cachedModels !== null) {
            return $this->cachedModels->values();
        }

        $this->cachedModels = $this->getDefaultModels();

        // Merge with any custom models from config
        $customModels = $this->config['models'] ?? [];
        foreach ($customModels as $id => $modelConfig) {
            $this->cachedModels->put($id, Model::from(array_merge(
                ['id' => $id, 'provider' => $this->providerId],
                $modelConfig
            )));
        }

        return $this->cachedModels->values();
    }

    /**
     * Get a specific model.
     */
    public function model(string $id): Model
    {
        $this->models(); // Ensure models are loaded

        if (! $this->cachedModels->has($id)) {
            throw ProviderException::modelNotFound($this->providerId, $id);
        }

        return $this->cachedModels->get($id);
    }

    /**
     * Count tokens for content.
     *
     * @param  string|array<int, mixed>|Message  $content
     */
    public function countTokens(string|array|Message $content, ?string $model = null): int
    {
        // Rough estimation: ~4 characters per token for English text
        $text = $this->extractText($content);

        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Send a chat completion request.
     */
    public function chat(ChatRequest $request): ChatResponse
    {
        $params = $this->buildRequestParams($request);
        $model = $request->model ?? $this->defaultModel();

        $this->dispatchCortexEvent(new BeforeProviderRequest(
            provider: $this->providerId,
            request: $request,
            model: $model,
        ));

        $startTime = microtime(true);

        try {
            $result = $this->client->converse($params);
            $response = $this->parseResponse($result->toArray(), $request);
            $duration = microtime(true) - $startTime;

            $this->dispatchCortexEvent(new AfterProviderResponse(
                provider: $this->providerId,
                request: $request,
                response: $response,
                duration: $duration,
            ));

            return $response;
        } catch (\Aws\Exception\AwsException $e) {
            $this->dispatchCortexEvent(new ProviderError(
                provider: $this->providerId,
                request: $request,
                exception: $e,
            ));

            throw $this->handleAwsException($e, $request);
        }
    }

    /**
     * Stream a chat completion request.
     */
    public function stream(ChatRequest $request): StreamedResponse
    {
        $params = $this->buildRequestParams($request);
        $model = $request->model ?? $this->defaultModel();

        $this->dispatchCortexEvent(new BeforeProviderRequest(
            provider: $this->providerId,
            request: $request,
            model: $model,
        ));

        return new StreamedResponse(function () use ($params, $request): Generator {
            try {
                $result = $this->client->converseStream($params);
                $eventStream = $result->get('stream');

                $index = 0;
                $currentToolUse = null;
                $toolUseInput = '';

                foreach ($eventStream as $event) {
                    if (isset($event['contentBlockStart']['toolUse'])) {
                        $toolData = $event['contentBlockStart']['toolUse'];
                        $currentToolUse = [
                            'id' => $toolData['toolUseId'],
                            'name' => $toolData['name'],
                        ];
                        $toolUseInput = '';
                    }

                    if (isset($event['contentBlockDelta']['delta']['text'])) {
                        yield $index => StreamChunk::textDelta(
                            $event['contentBlockDelta']['delta']['text'],
                            $index
                        );
                        $index++;
                    }

                    if (isset($event['contentBlockDelta']['delta']['toolUse']['input'])) {
                        $toolUseInput .= $event['contentBlockDelta']['delta']['toolUse']['input'];
                    }

                    if (isset($event['contentBlockStop']) && $currentToolUse !== null) {
                        $input = json_decode($toolUseInput, true) ?? [];
                        yield $index => StreamChunk::toolUseStart(
                            new ToolUseContent(
                                $currentToolUse['id'],
                                $currentToolUse['name'],
                                $input
                            ),
                            $index
                        );
                        $index++;
                        $currentToolUse = null;
                        $toolUseInput = '';
                    }

                    if (isset($event['messageStop'])) {
                        $stopReason = $this->mapStopReason($event['messageStop']['stopReason'] ?? 'end_turn');

                        yield $index => StreamChunk::messageComplete(
                            stopReason: $stopReason,
                            index: $index
                        );
                    }

                    if (isset($event['metadata']['usage'])) {
                        $usageData = $event['metadata']['usage'];
                        yield $index => new StreamChunk(
                            type: \JayI\Cortex\Plugins\Chat\StreamChunkType::MessageComplete,
                            usage: new Usage(
                                inputTokens: $usageData['inputTokens'] ?? 0,
                                outputTokens: $usageData['outputTokens'] ?? 0,
                            ),
                            index: $index
                        );
                    }
                }
            } catch (\Aws\Exception\AwsException $e) {
                $this->dispatchCortexEvent(new ProviderError(
                    provider: $this->providerId,
                    request: $request,
                    exception: $e,
                ));

                throw $this->handleAwsException($e, $request);
            }
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
        $mergedConfig = array_merge($this->config, $config);

        // Handle nested credentials array
        if (isset($config['credentials']) && isset($this->config['credentials'])) {
            $mergedConfig['credentials'] = array_merge(
                $this->config['credentials'],
                $config['credentials']
            );
        }

        return new static(
            $this->providerId,
            $mergedConfig,
            $this->container,
        );
    }

    /**
     * Get the default model ID.
     */
    public function defaultModel(): string
    {
        return $this->config['default_model'] ?? 'anthropic.claude-3-5-sonnet-20241022-v2:0';
    }

    /**
     * Create the Bedrock client.
     */
    protected function createClient(): BedrockRuntimeClient
    {
        $clientConfig = [
            'version' => $this->config['version'] ?? 'latest',
            'region' => $this->config['region'] ?? 'us-east-1',
        ];

        // Add credentials if explicitly provided
        if (isset($this->config['credentials']['key']) && isset($this->config['credentials']['secret'])) {
            $clientConfig['credentials'] = [
                'key' => $this->config['credentials']['key'],
                'secret' => $this->config['credentials']['secret'],
            ];
        }

        return new BedrockRuntimeClient($clientConfig);
    }

    /**
     * Build request parameters for the Converse API.
     *
     * @return array<string, mixed>
     */
    protected function buildRequestParams(ChatRequest $request): array
    {
        $model = $request->model ?? $this->defaultModel();

        $params = [
            'modelId' => $model,
            'messages' => $this->formatMessages($request),
        ];

        // Add system message
        $systemPrompt = $request->getEffectiveSystemPrompt();
        if ($systemPrompt !== null) {
            $params['system'] = [
                ['text' => $systemPrompt],
            ];
        }

        // Add inference configuration
        $inferenceConfig = $this->buildInferenceConfig($request->options);
        if (! empty($inferenceConfig)) {
            $params['inferenceConfig'] = $inferenceConfig;
        }

        // Add tools if present
        if ($request->hasTools()) {
            $params['toolConfig'] = $this->buildToolConfig($request);
        }

        // Merge provider-specific options
        return array_merge($params, $this->providerOptions, $request->options->providerOptions);
    }

    /**
     * Format messages for Bedrock Converse API.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function formatMessages(ChatRequest $request): array
    {
        $messages = [];

        foreach ($request->messages as $message) {
            // Skip system messages (handled separately)
            if ($message->role === MessageRole::System) {
                continue;
            }

            $messages[] = [
                'role' => $this->mapRole($message->role),
                'content' => $this->formatContent($message->content),
            ];
        }

        return $messages;
    }

    /**
     * Format content for Bedrock API.
     *
     * @param  array<int, Content>  $contents
     * @return array<int, array<string, mixed>>
     */
    protected function formatContent(array $contents): array
    {
        $formatted = [];

        foreach ($contents as $content) {
            if ($content instanceof TextContent) {
                $formatted[] = ['text' => $content->text];
            } elseif ($content instanceof ImageContent) {
                $formatted[] = $this->formatImageContent($content);
            } elseif ($content instanceof DocumentContent) {
                $formatted[] = $this->formatDocumentContent($content);
            } elseif ($content instanceof ToolUseContent) {
                $formatted[] = [
                    'toolUse' => [
                        'toolUseId' => $content->id,
                        'name' => $content->name,
                        'input' => $content->input,
                    ],
                ];
            } elseif ($content instanceof ToolResultContent) {
                $formatted[] = [
                    'toolResult' => [
                        'toolUseId' => $content->toolUseId,
                        'content' => [['text' => is_string($content->result) ? $content->result : json_encode($content->result)]],
                        'status' => $content->isError ? 'error' : 'success',
                    ],
                ];
            }
        }

        return $formatted;
    }

    /**
     * Format image content for Bedrock.
     *
     * @return array<string, mixed>
     */
    protected function formatImageContent(ImageContent $content): array
    {
        $mediaType = str_replace('image/', '', $content->mediaType);

        if ($content->sourceType === SourceType::Base64) {
            return [
                'image' => [
                    'format' => $mediaType,
                    'source' => [
                        'bytes' => base64_decode($content->source),
                    ],
                ],
            ];
        }

        // For URLs, we'd need to fetch and convert - for now, throw
        throw ProviderException::featureNotSupported($this->providerId, 'image_urls');
    }

    /**
     * Format document content for Bedrock.
     *
     * @return array<string, mixed>
     */
    protected function formatDocumentContent(DocumentContent $content): array
    {
        $format = match ($content->mediaType) {
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/html' => 'html',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            default => 'txt',
        };

        return [
            'document' => [
                'format' => $format,
                'name' => $content->name ?? 'document',
                'source' => [
                    'bytes' => base64_decode($content->source),
                ],
            ],
        ];
    }

    /**
     * Build inference configuration.
     *
     * @return array<string, mixed>
     */
    protected function buildInferenceConfig(ChatOptions $options): array
    {
        $config = [];

        if ($options->maxTokens !== null) {
            $config['maxTokens'] = $options->maxTokens;
        }

        if ($options->temperature !== null) {
            $config['temperature'] = $options->temperature;
        }

        if ($options->topP !== null) {
            $config['topP'] = $options->topP;
        }

        if (! empty($options->stopSequences)) {
            $config['stopSequences'] = $options->stopSequences;
        }

        return $config;
    }

    /**
     * Build tool configuration.
     *
     * @return array<string, mixed>
     */
    protected function buildToolConfig(ChatRequest $request): array
    {
        $tools = [];
        foreach ($request->tools->toToolDefinitions() as $tool) {
            $tools[] = [
                'toolSpec' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'inputSchema' => [
                        'json' => $tool['input_schema'] ?? $tool['inputSchema'] ?? ['type' => 'object'],
                    ],
                ],
            ];
        }

        $config = ['tools' => $tools];

        // Handle tool choice
        if ($request->options->toolChoice !== null) {
            $config['toolChoice'] = match ($request->options->toolChoice) {
                'auto' => ['auto' => new \stdClass],
                'any' => ['any' => new \stdClass],
                'none' => ['auto' => new \stdClass],
                default => ['tool' => ['name' => $request->options->toolChoice]],
            };
        }

        return $config;
    }

    /**
     * Parse the API response.
     *
     * @param  array<string, mixed>  $result
     */
    protected function parseResponse(array $result, ChatRequest $request): ChatResponse
    {
        $content = [];

        foreach ($result['output']['message']['content'] ?? [] as $block) {
            if (isset($block['text'])) {
                $content[] = new TextContent($block['text']);
            } elseif (isset($block['toolUse'])) {
                $content[] = new ToolUseContent(
                    $block['toolUse']['toolUseId'],
                    $block['toolUse']['name'],
                    $block['toolUse']['input'] ?? [],
                );
            }
        }

        $usage = new Usage(
            inputTokens: $result['usage']['inputTokens'] ?? 0,
            outputTokens: $result['usage']['outputTokens'] ?? 0,
        );

        return new ChatResponse(
            message: new Message(
                role: MessageRole::Assistant,
                content: $content,
            ),
            usage: $usage,
            stopReason: $this->mapStopReason($result['stopReason'] ?? 'end_turn'),
            model: $request->model ?? $this->defaultModel(),
        );
    }

    /**
     * Map message role to Bedrock format.
     */
    protected function mapRole(MessageRole $role): string
    {
        return match ($role) {
            MessageRole::User, MessageRole::Tool => 'user',
            MessageRole::Assistant => 'assistant',
            MessageRole::System => 'user', // Should not reach here
        };
    }

    /**
     * Map Bedrock stop reason to StopReason enum.
     */
    protected function mapStopReason(string $reason): StopReason
    {
        return match ($reason) {
            'end_turn' => StopReason::EndTurn,
            'max_tokens' => StopReason::MaxTokens,
            'stop_sequence' => StopReason::StopSequence,
            'tool_use' => StopReason::ToolUse,
            'content_filtered' => StopReason::ContentFiltered,
            default => StopReason::EndTurn,
        };
    }

    /**
     * Extract text from content for token counting.
     *
     * @param  string|array<int, mixed>|Message  $content
     */
    protected function extractText(string|array|Message $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if ($content instanceof Message) {
            return $content->text() ?? '';
        }

        $text = '';
        foreach ($content as $item) {
            if (is_string($item)) {
                $text .= $item;
            } elseif ($item instanceof TextContent) {
                $text .= $item->text;
            }
        }

        return $text;
    }

    /**
     * Handle AWS exceptions.
     */
    protected function handleAwsException(\Aws\Exception\AwsException $e, ?ChatRequest $request = null): ProviderException
    {
        $code = $e->getAwsErrorCode();
        $message = $e->getAwsErrorMessage() ?? $e->getMessage();

        if ($code === 'ThrottlingException') {
            $this->dispatchCortexEvent(new ProviderRateLimited(
                provider: $this->providerId,
            ));

            return ProviderException::rateLimited($this->providerId);
        }

        return match ($code) {
            'AccessDeniedException', 'UnauthorizedException' => ProviderException::authenticationFailed($this->providerId),
            'ValidationException' => ProviderException::apiError($this->providerId, "Validation error: {$message}", 400, $e),
            'ModelNotReadyException' => ProviderException::apiError($this->providerId, "Model not ready: {$message}", 503, $e),
            default => ProviderException::apiError($this->providerId, $message, $e->getStatusCode() ?? 500, $e),
        };
    }

    /**
     * Get default model definitions.
     *
     * @return Collection<string, Model>
     */
    protected function getDefaultModels(): Collection
    {
        return new Collection([
            'anthropic.claude-3-5-sonnet-20241022-v2:0' => new Model(
                id: 'anthropic.claude-3-5-sonnet-20241022-v2:0',
                name: 'Claude 3.5 Sonnet v2',
                provider: $this->providerId,
                contextWindow: 200000,
                maxOutputTokens: 8192,
                inputCostPer1kTokens: 0.003,
                outputCostPer1kTokens: 0.015,
                capabilities: $this->capabilities(),
            ),
            'anthropic.claude-3-5-haiku-20241022-v1:0' => new Model(
                id: 'anthropic.claude-3-5-haiku-20241022-v1:0',
                name: 'Claude 3.5 Haiku',
                provider: $this->providerId,
                contextWindow: 200000,
                maxOutputTokens: 8192,
                inputCostPer1kTokens: 0.001,
                outputCostPer1kTokens: 0.005,
                capabilities: $this->capabilities(),
            ),
            'anthropic.claude-3-opus-20240229-v1:0' => new Model(
                id: 'anthropic.claude-3-opus-20240229-v1:0',
                name: 'Claude 3 Opus',
                provider: $this->providerId,
                contextWindow: 200000,
                maxOutputTokens: 4096,
                inputCostPer1kTokens: 0.015,
                outputCostPer1kTokens: 0.075,
                capabilities: $this->capabilities(),
            ),
        ]);
    }
}
