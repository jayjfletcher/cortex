<?php

declare(strict_types=1);

use JayI\Cortex\Exceptions\StructuredOutputException;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationError;
use JayI\Cortex\Plugins\StructuredOutput\StructuredResponse;

describe('StructuredResponse', function () {
    it('creates a valid response', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->property('age', Schema::integer());

        $rawResponse = new ChatResponse(
            message: Message::assistant('{"name": "John", "age": 30}'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $response = StructuredResponse::valid(
            data: ['name' => 'John', 'age' => 30],
            schema: $schema,
            rawResponse: $rawResponse,
        );

        expect($response->valid)->toBeTrue();
        expect($response->data)->toBe(['name' => 'John', 'age' => 30]);
        expect($response->validationErrors)->toBeEmpty();
    });

    it('creates an invalid response', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->required('name');

        $rawResponse = new ChatResponse(
            message: Message::assistant('{}'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $errors = [
            new ValidationError('name', 'Required property "name" is missing', null),
        ];

        $response = StructuredResponse::invalid(
            data: [],
            schema: $schema,
            errors: $errors,
            rawResponse: $rawResponse,
        );

        expect($response->valid)->toBeFalse();
        expect($response->validationErrors)->toHaveCount(1);
        expect($response->errorMessages()[0])->toContain('Required');
    });

    it('converts to array', function () {
        $schema = Schema::object()->property('key', Schema::string());
        $rawResponse = new ChatResponse(
            message: Message::assistant('{"key": "value"}'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $response = StructuredResponse::valid(
            data: ['key' => 'value'],
            schema: $schema,
            rawResponse: $rawResponse,
        );

        expect($response->toArray())->toBe(['key' => 'value']);
    });

    it('gets value by key', function () {
        $schema = Schema::object()->property('name', Schema::string());
        $rawResponse = new ChatResponse(
            message: Message::assistant('{}'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $response = StructuredResponse::valid(
            data: ['name' => 'John', 'email' => 'john@example.com'],
            schema: $schema,
            rawResponse: $rawResponse,
        );

        expect($response->get('name'))->toBe('John');
        expect($response->get('missing', 'default'))->toBe('default');
    });

    it('throws on invalid when throw() called', function () {
        $schema = Schema::object()->property('name', Schema::string())->required('name');
        $rawResponse = new ChatResponse(
            message: Message::assistant('{}'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $response = StructuredResponse::invalid(
            data: [],
            schema: $schema,
            errors: [new ValidationError('name', 'Required', null)],
            rawResponse: $rawResponse,
        );

        expect(fn () => $response->throw())
            ->toThrow(StructuredOutputException::class);
    });

    it('returns self when throw() on valid', function () {
        $schema = Schema::object();
        $rawResponse = new ChatResponse(
            message: Message::assistant('{}'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $response = StructuredResponse::valid(
            data: [],
            schema: $schema,
            rawResponse: $rawResponse,
        );

        expect($response->throw())->toBe($response);
    });

    it('converts non-array data to array', function () {
        $schema = Schema::string();
        $rawResponse = new ChatResponse(
            message: Message::assistant('"test"'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $response = StructuredResponse::valid(
            data: 'test',
            schema: $schema,
            rawResponse: $rawResponse,
        );

        expect($response->toArray())->toBe(['data' => 'test']);
    });

    it('returns default when get on non-array data', function () {
        $schema = Schema::string();
        $rawResponse = new ChatResponse(
            message: Message::assistant('"test"'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $response = StructuredResponse::valid(
            data: 'test',
            schema: $schema,
            rawResponse: $rawResponse,
        );

        expect($response->get('key', 'default'))->toBe('default');
    });

    it('throws when toData called on invalid response', function () {
        $schema = Schema::object()->property('name', Schema::string())->required('name');
        $rawResponse = new ChatResponse(
            message: Message::assistant('{}'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $response = StructuredResponse::invalid(
            data: [],
            schema: $schema,
            errors: [new ValidationError('name', 'Required', null)],
            rawResponse: $rawResponse,
        );

        expect(fn () => $response->toData(stdClass::class))
            ->toThrow(StructuredOutputException::class);
    });

    it('throws when toData called with non-array data', function () {
        $schema = Schema::string();
        $rawResponse = new ChatResponse(
            message: Message::assistant('"test"'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $response = StructuredResponse::valid(
            data: 'test',
            schema: $schema,
            rawResponse: $rawResponse,
        );

        expect(fn () => $response->toData(stdClass::class))
            ->toThrow(StructuredOutputException::class);
    });
});

describe('StructuredOutputException', function () {
    it('creates validation failed exception', function () {
        $errors = [
            new ValidationError('field1', 'Error 1', 'value1'),
            new ValidationError('field2', 'Error 2', 'value2'),
        ];

        $exception = StructuredOutputException::validationFailed($errors);

        expect($exception->getMessage())->toContain('Error 1');
        expect($exception->getMessage())->toContain('Error 2');
    });

    it('creates parse failed exception', function () {
        $exception = StructuredOutputException::parseFailed('Invalid JSON', '{"broken}');

        expect($exception->getMessage())->toContain('Invalid JSON');
        expect($exception->context()['raw_content'])->toBe('{"broken}');
    });

    it('creates max retries exceeded exception', function () {
        $exception = StructuredOutputException::maxRetriesExceeded(3);

        expect($exception->getMessage())->toContain('3 attempts');
    });
});

describe('StructuredOutputHandler', function () {
    beforeEach(function () {
        $this->mockProviderRegistry = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract::class);
    });

    it('generates structured output with native strategy', function () {
        $schema = Schema::object()->property('name', Schema::string());

        $capabilities = new \JayI\Cortex\Plugins\Provider\ProviderCapabilities(
            structuredOutput: true,
            jsonMode: true,
        );

        $mockProvider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $mockProvider->shouldReceive('capabilities')->andReturn($capabilities);
        $mockProvider->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: Message::assistant('{"name": "John"}'),
                usage: Usage::zero(),
                stopReason: StopReason::EndTurn,
            ));

        $this->mockProviderRegistry->shouldReceive('get')
            ->with('claude-3')
            ->andReturn($mockProvider);

        $handler = new \JayI\Cortex\Plugins\StructuredOutput\StructuredOutputHandler(
            $this->mockProviderRegistry,
        );

        $request = new \JayI\Cortex\Plugins\Chat\ChatRequest(
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection([
                Message::user('Hello'),
            ]),
            model: 'claude-3',
        );

        $result = $handler->generate($request, $schema);

        expect($result->valid)->toBeTrue();
        expect($result->data)->toBe(['name' => 'John']);
    });

    it('generates structured output with json_mode strategy', function () {
        $schema = Schema::object()->property('name', Schema::string());

        $capabilities = new \JayI\Cortex\Plugins\Provider\ProviderCapabilities(
            structuredOutput: false,
            jsonMode: true,
        );

        $mockProvider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $mockProvider->shouldReceive('capabilities')->andReturn($capabilities);
        $mockProvider->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: Message::assistant('{"name": "Jane"}'),
                usage: Usage::zero(),
                stopReason: StopReason::EndTurn,
            ));

        $this->mockProviderRegistry->shouldReceive('get')
            ->with('claude-3')
            ->andReturn($mockProvider);

        $handler = new \JayI\Cortex\Plugins\StructuredOutput\StructuredOutputHandler(
            $this->mockProviderRegistry,
        );

        $request = new \JayI\Cortex\Plugins\Chat\ChatRequest(
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection([
                Message::user('Hello'),
            ]),
            model: 'claude-3',
        );

        $result = $handler->generate($request, $schema);

        expect($result->valid)->toBeTrue();
        expect($result->data)->toBe(['name' => 'Jane']);
    });

    it('generates structured output with prompt strategy', function () {
        $schema = Schema::object()->property('name', Schema::string());

        $capabilities = new \JayI\Cortex\Plugins\Provider\ProviderCapabilities(
            structuredOutput: false,
            jsonMode: false,
        );

        $mockProvider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $mockProvider->shouldReceive('capabilities')->andReturn($capabilities);
        $mockProvider->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: Message::assistant('{"name": "Bob"}'),
                usage: Usage::zero(),
                stopReason: StopReason::EndTurn,
            ));

        $this->mockProviderRegistry->shouldReceive('get')
            ->with('claude-3')
            ->andReturn($mockProvider);

        $handler = new \JayI\Cortex\Plugins\StructuredOutput\StructuredOutputHandler(
            $this->mockProviderRegistry,
            ['retry' => ['enabled' => false]],
        );

        $request = new \JayI\Cortex\Plugins\Chat\ChatRequest(
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection([
                Message::user('Hello'),
            ]),
            model: 'claude-3',
        );

        $result = $handler->generate($request, $schema);

        expect($result->valid)->toBeTrue();
        expect($result->data)->toBe(['name' => 'Bob']);
    });

    it('uses configured strategy instead of auto', function () {
        $schema = Schema::object()->property('name', Schema::string());

        $capabilities = new \JayI\Cortex\Plugins\Provider\ProviderCapabilities(
            structuredOutput: true,
            jsonMode: true,
        );

        $mockProvider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $mockProvider->shouldReceive('capabilities')->andReturn($capabilities);
        $mockProvider->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: Message::assistant('{"name": "Alice"}'),
                usage: Usage::zero(),
                stopReason: StopReason::EndTurn,
            ));

        $this->mockProviderRegistry->shouldReceive('get')
            ->with('claude-3')
            ->andReturn($mockProvider);

        $handler = new \JayI\Cortex\Plugins\StructuredOutput\StructuredOutputHandler(
            $this->mockProviderRegistry,
            ['strategy' => 'prompt', 'retry' => ['enabled' => false]],
        );

        $request = new \JayI\Cortex\Plugins\Chat\ChatRequest(
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection([
                Message::user('Hello'),
            ]),
            model: 'claude-3',
        );

        $result = $handler->generate($request, $schema);

        expect($result->valid)->toBeTrue();
    });

    it('returns invalid response for non-JSON content', function () {
        $schema = Schema::object()->property('name', Schema::string());

        $capabilities = new \JayI\Cortex\Plugins\Provider\ProviderCapabilities(
            structuredOutput: true,
        );

        $mockProvider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $mockProvider->shouldReceive('capabilities')->andReturn($capabilities);
        $mockProvider->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: Message::assistant('This is not JSON'),
                usage: Usage::zero(),
                stopReason: StopReason::EndTurn,
            ));

        $this->mockProviderRegistry->shouldReceive('get')
            ->with('claude-3')
            ->andReturn($mockProvider);

        $handler = new \JayI\Cortex\Plugins\StructuredOutput\StructuredOutputHandler(
            $this->mockProviderRegistry,
        );

        $request = new \JayI\Cortex\Plugins\Chat\ChatRequest(
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection([
                Message::user('Hello'),
            ]),
            model: 'claude-3',
        );

        $result = $handler->generate($request, $schema);

        expect($result->valid)->toBeFalse();
        expect($result->validationErrors)->toHaveCount(1);
    });

    it('returns invalid response for invalid JSON schema', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->required('name');

        $capabilities = new \JayI\Cortex\Plugins\Provider\ProviderCapabilities(
            structuredOutput: true,
        );

        $mockProvider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $mockProvider->shouldReceive('capabilities')->andReturn($capabilities);
        $mockProvider->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: Message::assistant('{}'),
                usage: Usage::zero(),
                stopReason: StopReason::EndTurn,
            ));

        $this->mockProviderRegistry->shouldReceive('get')
            ->with('claude-3')
            ->andReturn($mockProvider);

        $handler = new \JayI\Cortex\Plugins\StructuredOutput\StructuredOutputHandler(
            $this->mockProviderRegistry,
        );

        $request = new \JayI\Cortex\Plugins\Chat\ChatRequest(
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection([
                Message::user('Hello'),
            ]),
            model: 'claude-3',
        );

        $result = $handler->generate($request, $schema);

        expect($result->valid)->toBeFalse();
    });

    it('parses JSON from markdown code block', function () {
        $schema = Schema::object()->property('name', Schema::string());

        $capabilities = new \JayI\Cortex\Plugins\Provider\ProviderCapabilities(
            structuredOutput: true,
        );

        $mockProvider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $mockProvider->shouldReceive('capabilities')->andReturn($capabilities);
        $mockProvider->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: Message::assistant("Here's the result:\n```json\n{\"name\": \"Test\"}\n```"),
                usage: Usage::zero(),
                stopReason: StopReason::EndTurn,
            ));

        $this->mockProviderRegistry->shouldReceive('get')
            ->with('claude-3')
            ->andReturn($mockProvider);

        $handler = new \JayI\Cortex\Plugins\StructuredOutput\StructuredOutputHandler(
            $this->mockProviderRegistry,
        );

        $request = new \JayI\Cortex\Plugins\Chat\ChatRequest(
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection([
                Message::user('Hello'),
            ]),
            model: 'claude-3',
        );

        $result = $handler->generate($request, $schema);

        expect($result->valid)->toBeTrue();
        expect($result->data)->toBe(['name' => 'Test']);
    });

    it('extracts JSON object from text', function () {
        $schema = Schema::object()->property('name', Schema::string());

        $capabilities = new \JayI\Cortex\Plugins\Provider\ProviderCapabilities(
            structuredOutput: true,
        );

        $mockProvider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $mockProvider->shouldReceive('capabilities')->andReturn($capabilities);
        $mockProvider->shouldReceive('chat')
            ->once()
            ->andReturn(new ChatResponse(
                message: Message::assistant('Here is the answer: {"name": "Extracted"} and more text'),
                usage: Usage::zero(),
                stopReason: StopReason::EndTurn,
            ));

        $this->mockProviderRegistry->shouldReceive('get')
            ->with('claude-3')
            ->andReturn($mockProvider);

        $handler = new \JayI\Cortex\Plugins\StructuredOutput\StructuredOutputHandler(
            $this->mockProviderRegistry,
        );

        $request = new \JayI\Cortex\Plugins\Chat\ChatRequest(
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection([
                Message::user('Hello'),
            ]),
            model: 'claude-3',
        );

        $result = $handler->generate($request, $schema);

        expect($result->valid)->toBeTrue();
        expect($result->data)->toBe(['name' => 'Extracted']);
    });
});
