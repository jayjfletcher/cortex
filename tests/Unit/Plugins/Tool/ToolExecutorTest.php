<?php

declare(strict_types=1);

use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Exceptions\ToolException;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Contracts\ToolRegistryContract;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolExecutor;
use JayI\Cortex\Plugins\Tool\ToolResult;

describe('ToolExecutor', function () {
    it('executes a tool by name', function () {
        $tool = Tool::make('greet')
            ->withInput(Schema::object()->property('name', Schema::string())->required('name'))
            ->withHandler(fn ($input) => ToolResult::success("Hello, {$input['name']}!"));

        $registry = Mockery::mock(ToolRegistryContract::class);
        $registry->shouldReceive('get')
            ->once()
            ->with('greet')
            ->andReturn($tool);

        $executor = new ToolExecutor($registry);
        $result = $executor->execute('greet', ['name' => 'World']);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('Hello, World!');
    });

    it('executes a tool instance directly', function () {
        $tool = Tool::make('add')
            ->withInput(Schema::object()
                ->property('a', Schema::integer())
                ->property('b', Schema::integer())
                ->required('a', 'b'))
            ->withHandler(fn ($input) => ToolResult::success($input['a'] + $input['b']));

        $registry = Mockery::mock(ToolRegistryContract::class);

        $executor = new ToolExecutor($registry);
        $result = $executor->executeTool($tool, ['a' => 5, 'b' => 3]);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe(8);
    });

    it('returns error for invalid input', function () {
        $tool = Tool::make('validate')
            ->withInput(Schema::object()
                ->property('email', Schema::string()->format('email'))
                ->required('email'))
            ->withHandler(fn ($input) => ToolResult::success($input['email']));

        $registry = Mockery::mock(ToolRegistryContract::class);

        $executor = new ToolExecutor($registry);
        $result = $executor->executeTool($tool, ['email' => 'not-an-email']);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('validation failed');
    });

    it('returns error when required field is missing', function () {
        $tool = Tool::make('test')
            ->withInput(Schema::object()
                ->property('required_field', Schema::string())
                ->required('required_field'))
            ->withHandler(fn ($input) => ToolResult::success('ok'));

        $registry = Mockery::mock(ToolRegistryContract::class);

        $executor = new ToolExecutor($registry);
        $result = $executor->executeTool($tool, []);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('validation failed');
    });

    it('applies before and after hooks', function () {
        $tool = Tool::make('hooked')
            ->withInput(Schema::object()->property('value', Schema::integer()))
            ->withHandler(fn ($input) => ToolResult::success($input['value'] * 2));

        $registry = Mockery::mock(ToolRegistryContract::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        // Before hook doubles the value
        $pluginManager->shouldReceive('applyHooks')
            ->once()
            ->with('tool.before_execute', ['value' => 5], $tool, Mockery::type(ToolContext::class))
            ->andReturn(['value' => 10]);

        // After hook modifies result
        $pluginManager->shouldReceive('applyHooks')
            ->once()
            ->with('tool.after_execute', Mockery::type(ToolResult::class), $tool, ['value' => 10], Mockery::type(ToolContext::class))
            ->andReturnUsing(fn ($hook, $result) => $result);

        $executor = new ToolExecutor($registry, $pluginManager);
        $result = $executor->executeTool($tool, ['value' => 5]);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe(20); // 10 * 2 from modified input
    });

    it('catches and returns errors from tool execution', function () {
        $tool = Tool::make('failing')
            ->withInput(Schema::object())
            ->withHandler(fn () => throw new RuntimeException('Something went wrong'));

        $registry = Mockery::mock(ToolRegistryContract::class);

        $executor = new ToolExecutor($registry);
        $result = $executor->executeTool($tool, []);

        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Something went wrong');
    });

    it('rethrows ToolException', function () {
        $tool = Tool::make('tool_exception')
            ->withInput(Schema::object())
            ->withHandler(fn () => throw ToolException::notFound('test'));

        $registry = Mockery::mock(ToolRegistryContract::class);

        $executor = new ToolExecutor($registry);

        expect(fn () => $executor->executeTool($tool, []))
            ->toThrow(ToolException::class);
    });

    it('executes many tools in sequence', function () {
        $tool1 = Tool::make('tool1')
            ->withInput(Schema::object())
            ->withHandler(fn () => ToolResult::success('result1'));

        $tool2 = Tool::make('tool2')
            ->withInput(Schema::object())
            ->withHandler(fn () => ToolResult::success('result2'));

        $registry = Mockery::mock(ToolRegistryContract::class);
        $registry->shouldReceive('get')
            ->with('tool1')
            ->andReturn($tool1);
        $registry->shouldReceive('get')
            ->with('tool2')
            ->andReturn($tool2);

        $executor = new ToolExecutor($registry);
        $results = $executor->executeMany([
            ['name' => 'tool1', 'input' => []],
            ['name' => 'tool2', 'input' => []],
        ]);

        expect($results)->toHaveCount(2);
        expect($results[0]->output)->toBe('result1');
        expect($results[1]->output)->toBe('result2');
    });

    it('creates default context when not provided', function () {
        $tool = Tool::make('context_test')
            ->withInput(Schema::object())
            ->withHandler(fn ($input, $context) => ToolResult::success($context instanceof ToolContext));

        $registry = Mockery::mock(ToolRegistryContract::class);
        $registry->shouldReceive('get')
            ->with('context_test')
            ->andReturn($tool);

        $executor = new ToolExecutor($registry);
        $result = $executor->execute('context_test', []);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBeTrue();
    });

    it('passes provided context to tool', function () {
        $tool = Tool::make('with_context')
            ->withInput(Schema::object())
            ->withHandler(fn ($input, $context) => ToolResult::success($context->conversationId));

        $registry = Mockery::mock(ToolRegistryContract::class);

        $context = ToolContext::forConversation('conv-123');

        $executor = new ToolExecutor($registry);
        $result = $executor->executeTool($tool, [], $context);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('conv-123');
    });

    it('executes tool without timeout when null', function () {
        $tool = Tool::make('no_timeout')
            ->withInput(Schema::object())
            ->withHandler(fn () => ToolResult::success('done'));

        expect($tool->timeout())->toBeNull();

        $registry = Mockery::mock(ToolRegistryContract::class);

        $executor = new ToolExecutor($registry);
        $result = $executor->executeTool($tool, []);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('done');
    });

    it('executes tool with timeout', function () {
        $tool = Tool::make('with_timeout')
            ->withInput(Schema::object())
            ->withTimeout(30)
            ->withHandler(fn () => ToolResult::success('completed'));

        expect($tool->timeout())->toBe(30);

        $registry = Mockery::mock(ToolRegistryContract::class);

        $executor = new ToolExecutor($registry);
        $result = $executor->executeTool($tool, []);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('completed');
    });
});

describe('ToolException', function () {
    it('creates not found exception', function () {
        $exception = ToolException::notFound('test_tool');

        expect($exception)->toBeInstanceOf(ToolException::class);
        expect($exception->getMessage())->toContain('test_tool');
        expect($exception->getMessage())->toContain('not found');
    });

    it('creates already registered exception', function () {
        $exception = ToolException::alreadyRegistered('test_tool');

        expect($exception)->toBeInstanceOf(ToolException::class);
        expect($exception->getMessage())->toContain('test_tool');
        expect($exception->getMessage())->toContain('already registered');
    });

    it('creates execution failed exception', function () {
        $previous = new RuntimeException('Inner error');
        $exception = ToolException::executionFailed('test_tool', 'Something went wrong', $previous);

        expect($exception)->toBeInstanceOf(ToolException::class);
        expect($exception->getMessage())->toContain('test_tool');
        expect($exception->getMessage())->toContain('execution failed');
        expect($exception->getPrevious())->toBe($previous);
    });

    it('creates timeout exception', function () {
        $exception = ToolException::timeout('slow_tool', 30);

        expect($exception)->toBeInstanceOf(ToolException::class);
        expect($exception->getMessage())->toContain('slow_tool');
        expect($exception->getMessage())->toContain('timed out');
        expect($exception->getMessage())->toContain('30');
    });

    it('creates invalid class exception', function () {
        $exception = ToolException::invalidClass('BadToolClass', 'Class does not exist');

        expect($exception)->toBeInstanceOf(ToolException::class);
        expect($exception->getMessage())->toContain('BadToolClass');
        expect($exception->getMessage())->toContain('Class does not exist');
    });

    it('creates validation failed exception', function () {
        $exception = ToolException::validationFailed('validate_tool', ['email' => 'Invalid email']);

        expect($exception)->toBeInstanceOf(ToolException::class);
        expect($exception->getMessage())->toContain('validate_tool');
        expect($exception->getMessage())->toContain('validation failed');
    });
});
