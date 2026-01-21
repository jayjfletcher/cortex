<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\StructuredOutput;

use JayI\Cortex\Exceptions\StructuredOutputException;
use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\SchemaFactory;
use JayI\Cortex\Plugins\StructuredOutput\Contracts\StructuredOutputContract;
use Spatie\LaravelData\Data;

class StructuredOutputHandler implements StructuredOutputContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected ProviderRegistryContract $providers,
        protected array $config = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function generate(ChatRequest $request, Schema $schema): StructuredResponse
    {
        $provider = $this->providers->get($request->model);
        $strategy = $this->determineStrategy($provider);

        return match ($strategy) {
            'native' => $this->generateNative($provider, $request, $schema),
            'json_mode' => $this->generateJsonMode($provider, $request, $schema),
            'prompt' => $this->generatePromptBased($provider, $request, $schema),
            default => $this->generatePromptBased($provider, $request, $schema),
        };
    }

    /**
     * {@inheritdoc}
     */
    public function generateAs(ChatRequest $request, string $dataClass): object
    {
        // Generate schema from Data class
        $schema = SchemaFactory::fromDataClass($dataClass);

        $response = $this->generate($request, $schema);

        return $response->throw()->toData($dataClass);
    }

    /**
     * Determine the best strategy for structured output.
     */
    protected function determineStrategy(ProviderContract $provider): string
    {
        $configStrategy = $this->config['strategy'] ?? 'auto';

        if ($configStrategy !== 'auto') {
            return $configStrategy;
        }

        $capabilities = $provider->capabilities();

        if ($capabilities->structuredOutput) {
            return 'native';
        }

        if ($capabilities->jsonMode) {
            return 'json_mode';
        }

        return 'prompt';
    }

    /**
     * Generate using native structured output support.
     */
    protected function generateNative(ProviderContract $provider, ChatRequest $request, Schema $schema): StructuredResponse
    {
        // Add schema to request
        $request = new ChatRequest(
            messages: $request->messages,
            systemPrompt: $request->systemPrompt,
            model: $request->model,
            options: $request->options,
            tools: $request->tools,
            responseSchema: $schema,
            metadata: $request->metadata,
        );

        $response = $provider->chat($request);

        return $this->processResponse($response, $schema);
    }

    /**
     * Generate using JSON mode with schema in prompt.
     */
    protected function generateJsonMode(ProviderContract $provider, ChatRequest $request, Schema $schema): StructuredResponse
    {
        // Add schema instruction to system prompt
        $schemaInstruction = $this->buildSchemaPrompt($schema);
        $systemPrompt = $request->getEffectiveSystemPrompt() ?? '';
        $systemPrompt .= "\n\n{$schemaInstruction}";

        // Create new request with JSON mode option
        $options = $request->options;
        $providerOptions = array_merge($options->providerOptions, ['json_mode' => true]);
        $newOptions = new \JayI\Cortex\Plugins\Chat\ChatOptions(
            temperature: $options->temperature,
            maxTokens: $options->maxTokens,
            topP: $options->topP,
            topK: $options->topK,
            stopSequences: $options->stopSequences,
            toolChoice: $options->toolChoice,
            providerOptions: $providerOptions,
        );

        $request = new ChatRequest(
            messages: $request->messages,
            systemPrompt: $systemPrompt,
            model: $request->model,
            options: $newOptions,
            tools: $request->tools,
            responseSchema: null,
            metadata: $request->metadata,
        );

        $response = $provider->chat($request);

        return $this->processResponse($response, $schema);
    }

    /**
     * Generate using prompt-based approach.
     */
    protected function generatePromptBased(ProviderContract $provider, ChatRequest $request, Schema $schema): StructuredResponse
    {
        // Add schema instruction to system prompt
        $schemaInstruction = $this->buildSchemaPrompt($schema);
        $systemPrompt = $request->getEffectiveSystemPrompt() ?? '';
        $systemPrompt .= "\n\n{$schemaInstruction}";

        // Prepend system message if needed
        $messages = $request->messages;
        if ($systemPrompt !== '') {
            $messages->prepend(Message::system($systemPrompt));
        }

        $request = new ChatRequest(
            messages: $messages,
            systemPrompt: null,
            model: $request->model,
            options: $request->options,
            tools: $request->tools,
            responseSchema: null,
            metadata: $request->metadata,
        );

        $response = $provider->chat($request);

        return $this->processResponseWithRetry($provider, $request, $response, $schema);
    }

    /**
     * Process the response and validate against schema.
     */
    protected function processResponse(ChatResponse $response, Schema $schema): StructuredResponse
    {
        $content = $response->content();

        // Try to parse JSON
        $data = $this->parseJson($content);

        if ($data === null) {
            return StructuredResponse::invalid(
                data: null,
                schema: $schema,
                errors: [new \JayI\Cortex\Plugins\Schema\ValidationError(
                    path: '',
                    message: 'Failed to parse response as JSON',
                    value: $content,
                )],
                rawResponse: $response,
            );
        }

        // Validate against schema
        $validation = $schema->validate($data);

        if (! $validation->isValid()) {
            return StructuredResponse::invalid(
                data: $data,
                schema: $schema,
                errors: $validation->errors,
                rawResponse: $response,
            );
        }

        return StructuredResponse::valid(
            data: $data,
            schema: $schema,
            rawResponse: $response,
        );
    }

    /**
     * Process response with retry on validation failure.
     */
    protected function processResponseWithRetry(
        ProviderContract $provider,
        ChatRequest $request,
        ChatResponse $response,
        Schema $schema
    ): StructuredResponse {
        $result = $this->processResponse($response, $schema);

        if ($result->valid) {
            return $result;
        }

        // Retry if configured
        $maxRetries = $this->config['retry']['max_attempts'] ?? 2;
        $retryEnabled = $this->config['retry']['enabled'] ?? true;

        if (! $retryEnabled || $maxRetries <= 0) {
            return $result;
        }

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            // Add error feedback to messages
            $errorMessage = "The previous response was invalid. Errors:\n";
            foreach ($result->validationErrors as $error) {
                $errorMessage .= "- {$error->message}\n";
            }
            $errorMessage .= "\nPlease provide a valid response.";

            $messages = $request->messages;
            $messages->assistant($response->content());
            $messages->user($errorMessage);

            $newRequest = new ChatRequest(
                messages: $messages,
                systemPrompt: $request->systemPrompt,
                model: $request->model,
                options: $request->options,
                tools: $request->tools,
                responseSchema: null,
                metadata: $request->metadata,
            );

            $response = $provider->chat($newRequest);
            $result = $this->processResponse($response, $schema);

            if ($result->valid) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * Build schema instruction for prompt.
     */
    protected function buildSchemaPrompt(Schema $schema): string
    {
        $jsonSchema = json_encode($schema->toJsonSchema(), JSON_PRETTY_PRINT);

        return <<<PROMPT
You must respond with valid JSON that matches the following JSON Schema:

```json
{$jsonSchema}
```

Only output the JSON object, nothing else. Do not include any explanation or text outside the JSON.
PROMPT;
    }

    /**
     * Parse JSON from content.
     */
    protected function parseJson(string $content): mixed
    {
        // Try direct parse
        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        // Try to extract JSON from markdown code block
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)\n?```/', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        // Try to find JSON object in content
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }

        return null;
    }
}
