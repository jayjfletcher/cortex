<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolResult;

describe('Tool', function () {
    it('creates a tool with fluent builder', function () {
        $tool = Tool::make('get_weather')
            ->withDescription('Get the current weather for a location')
            ->withInput(
                Schema::object()
                    ->property('location', Schema::string()->description('City name'))
                    ->property('unit', Schema::enum(['celsius', 'fahrenheit']))
                    ->required('location')
            )
            ->withHandler(function (array $input, ToolContext $context) {
                return ToolResult::success([
                    'temperature' => 22,
                    'conditions' => 'Sunny',
                ]);
            })
            ->withTimeout(30);

        expect($tool->name())->toBe('get_weather');
        expect($tool->description())->toBe('Get the current weather for a location');
        expect($tool->timeout())->toBe(30);
    });

    it('executes a tool handler', function () {
        $tool = Tool::make('echo')
            ->withDescription('Echo back the input')
            ->withInput(Schema::object()->property('message', Schema::string())->required('message'))
            ->withHandler(fn (array $input) => ToolResult::success($input['message']));

        $result = $tool->execute(['message' => 'Hello'], new ToolContext);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('Hello');
    });

    it('converts to definition array', function () {
        $tool = Tool::make('search')
            ->withDescription('Search the database')
            ->withInput(
                Schema::object()
                    ->property('query', Schema::string())
                    ->required('query')
            );

        $definition = $tool->toDefinition();

        expect($definition['name'])->toBe('search');
        expect($definition['description'])->toBe('Search the database');
        expect($definition['input_schema']['type'])->toBe('object');
        expect($definition['input_schema']['properties']['query']['type'])->toBe('string');
    });

    it('throws when no handler defined', function () {
        $tool = Tool::make('no_handler')
            ->withDescription('Tool without handler');

        expect(fn () => $tool->execute([], new ToolContext))
            ->toThrow(RuntimeException::class, "No handler defined for tool 'no_handler'");
    });

    it('wraps non-ToolResult returns in success', function () {
        $tool = Tool::make('simple')
            ->withDescription('Returns plain value')
            ->withInput(Schema::object())
            ->withHandler(fn () => ['key' => 'value']);

        $result = $tool->execute([], new ToolContext);

        expect($result)->toBeInstanceOf(ToolResult::class);
        expect($result->success)->toBeTrue();
        expect($result->output)->toBe(['key' => 'value']);
    });
});

describe('ToolResult', function () {
    it('creates a success result', function () {
        $result = ToolResult::success(['data' => 'value'], ['meta' => 'info']);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe(['data' => 'value']);
        expect($result->error)->toBeNull();
        expect($result->shouldContinue)->toBeTrue();
        expect($result->metadata)->toBe(['meta' => 'info']);
    });

    it('creates an error result', function () {
        $result = ToolResult::error('Something went wrong');

        expect($result->success)->toBeFalse();
        expect($result->output)->toBeNull();
        expect($result->error)->toBe('Something went wrong');
        expect($result->shouldContinue)->toBeTrue();
    });

    it('creates a stop result', function () {
        $result = ToolResult::stop('Final answer');

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('Final answer');
        expect($result->shouldContinue)->toBeFalse();
        expect($result->shouldStop())->toBeTrue();
    });

    it('converts output to content string', function () {
        $result = ToolResult::success(['temperature' => 22, 'unit' => 'celsius']);
        $content = $result->toContentString();

        expect($content)->toContain('temperature');
        expect($content)->toContain('22');

        $errorResult = ToolResult::error('Failed');
        expect($errorResult->toContentString())->toBe('Error: Failed');
    });
});

describe('ToolContext', function () {
    it('creates context with factory methods', function () {
        $forConversation = ToolContext::forConversation('conv-123');
        expect($forConversation->conversationId)->toBe('conv-123');

        $forAgent = ToolContext::forAgent('agent-1', 'conv-123');
        expect($forAgent->agentId)->toBe('agent-1');
        expect($forAgent->conversationId)->toBe('conv-123');

        $forTenant = ToolContext::forTenant('tenant-1');
        expect($forTenant->tenantId)->toBe('tenant-1');
    });

    it('adds metadata fluently', function () {
        $context = new ToolContext;
        $withMeta = $context->withMetadata(['key' => 'value']);

        expect($withMeta->get('key'))->toBe('value');
        expect($withMeta->get('missing', 'default'))->toBe('default');
    });
});
