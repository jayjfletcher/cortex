<?php

declare(strict_types=1);

use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\AgentResponse;
use JayI\Cortex\Plugins\Agent\AgentStopReason;
use JayI\Cortex\Plugins\Agent\Loops\SimpleAgentLoop;
use JayI\Cortex\Plugins\Agent\Memory\BufferMemory;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolExecutor;
use JayI\Cortex\Plugins\Tool\ToolResult;

describe('SimpleAgentLoop', function () {
    it('executes agent with simple text response', function () {
        $chatClient = Mockery::mock(ChatClientContract::class);
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        // Create a response without tool calls
        $response = new ChatResponse(
            message: Message::assistant('Hello! How can I help?'),
            usage: new Usage(100, 50),
            stopReason: StopReason::EndTurn,
        );

        $chatClient->shouldReceive('send')
            ->once()
            ->andReturn($response);

        $pluginManager->shouldReceive('applyHooks')
            ->andReturnUsing(fn ($hook, ...$args) => $args[0] ?? null);

        $loop = new SimpleAgentLoop($chatClient, $toolExecutor, $pluginManager);

        $agent = Agent::make('test-agent')
            ->withSystemPrompt('You are helpful.')
            ->withMaxIterations(10);

        $result = $loop->execute($agent, 'Hello!', new AgentContext());

        expect($result)->toBeInstanceOf(AgentResponse::class);
        expect($result->stopReason)->toBe(AgentStopReason::Completed);
        expect($result->content)->toBe('Hello! How can I help?');
        expect($result->iterations)->toHaveCount(1);
    });

    it('executes agent with tool calls', function () {
        $chatClient = Mockery::mock(ChatClientContract::class);
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        // First response: assistant calls a tool
        $toolUseContent = new ToolUseContent('tool_1', 'get_weather', ['location' => 'NYC']);
        $firstResponse = new ChatResponse(
            message: new Message(
                role: \JayI\Cortex\Plugins\Chat\MessageRole::Assistant,
                content: [$toolUseContent],
            ),
            usage: new Usage(100, 50),
            stopReason: StopReason::ToolUse,
        );

        // Second response: assistant provides final answer
        $secondResponse = new ChatResponse(
            message: Message::assistant('The weather in NYC is sunny.'),
            usage: new Usage(80, 40),
            stopReason: StopReason::EndTurn,
        );

        $chatClient->shouldReceive('send')
            ->twice()
            ->andReturn($firstResponse, $secondResponse);

        $toolExecutor->shouldReceive('executeTool')
            ->once()
            ->with(Mockery::type(Tool::class), ['location' => 'NYC'], Mockery::any())
            ->andReturn(ToolResult::success(['temp' => '72F', 'condition' => 'sunny']));

        $pluginManager->shouldReceive('applyHooks')
            ->andReturnUsing(fn ($hook, ...$args) => $args[0] ?? null);

        $loop = new SimpleAgentLoop($chatClient, $toolExecutor, $pluginManager);

        $tool = Tool::make('get_weather')
            ->withInput(Schema::object()->property('location', Schema::string()))
            ->withHandler(fn ($input) => ToolResult::success(['temp' => '72F']));

        $agent = Agent::make('weather-agent')
            ->withSystemPrompt('You are a weather assistant.')
            ->withMaxIterations(10)
            ->addTool($tool);

        $result = $loop->execute($agent, 'What is the weather in NYC?', new AgentContext());

        expect($result->stopReason)->toBe(AgentStopReason::Completed);
        expect($result->content)->toBe('The weather in NYC is sunny.');
        expect($result->iterations)->toHaveCount(2);
    });

    it('stops when tool returns stop result', function () {
        $chatClient = Mockery::mock(ChatClientContract::class);
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        // Response with tool call
        $toolUseContent = new ToolUseContent('tool_1', 'final_answer', ['answer' => 'done']);
        $response = new ChatResponse(
            message: new Message(
                role: \JayI\Cortex\Plugins\Chat\MessageRole::Assistant,
                content: [$toolUseContent],
            ),
            usage: new Usage(100, 50),
            stopReason: StopReason::ToolUse,
        );

        $chatClient->shouldReceive('send')
            ->once()
            ->andReturn($response);

        $toolExecutor->shouldReceive('executeTool')
            ->once()
            ->andReturn(ToolResult::stop('Final answer provided'));

        $pluginManager->shouldReceive('applyHooks')
            ->andReturnUsing(fn ($hook, ...$args) => $args[0] ?? null);

        $loop = new SimpleAgentLoop($chatClient, $toolExecutor, $pluginManager);

        $tool = Tool::make('final_answer')
            ->withInput(Schema::object())
            ->withHandler(fn ($input) => ToolResult::stop('done'));

        $agent = Agent::make('stopping-agent')
            ->withSystemPrompt('You are helpful.')
            ->withMaxIterations(10)
            ->addTool($tool);

        $result = $loop->execute($agent, 'Give me a final answer', new AgentContext());

        expect($result->stopReason)->toBe(AgentStopReason::ToolStopped);
        expect($result->iterations)->toHaveCount(1);
    });

    it('stops at max iterations', function () {
        $chatClient = Mockery::mock(ChatClientContract::class);
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        // Always return a tool call to keep iterating
        $toolUseContent = new ToolUseContent('tool_1', 'loop_tool', []);
        $response = new ChatResponse(
            message: new Message(
                role: \JayI\Cortex\Plugins\Chat\MessageRole::Assistant,
                content: [$toolUseContent],
            ),
            usage: new Usage(100, 50),
            stopReason: StopReason::ToolUse,
        );

        $chatClient->shouldReceive('send')
            ->times(3) // max iterations
            ->andReturn($response);

        $toolExecutor->shouldReceive('executeTool')
            ->times(3)
            ->andReturn(ToolResult::success('continuing'));

        $pluginManager->shouldReceive('applyHooks')
            ->andReturnUsing(fn ($hook, ...$args) => $args[0] ?? null);

        $loop = new SimpleAgentLoop($chatClient, $toolExecutor, $pluginManager);

        $tool = Tool::make('loop_tool')
            ->withInput(Schema::object())
            ->withHandler(fn ($input) => ToolResult::success('loop'));

        $agent = Agent::make('looping-agent')
            ->withSystemPrompt('You loop forever.')
            ->withMaxIterations(3) // Low limit
            ->addTool($tool);

        $result = $loop->execute($agent, 'Loop please', new AgentContext());

        expect($result->stopReason)->toBe(AgentStopReason::MaxIterations);
        expect($result->iterations)->toHaveCount(3);
    });

    it('handles tool not found', function () {
        $chatClient = Mockery::mock(ChatClientContract::class);
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        // Response calling a non-existent tool
        $toolUseContent = new ToolUseContent('tool_1', 'nonexistent_tool', []);
        $firstResponse = new ChatResponse(
            message: new Message(
                role: \JayI\Cortex\Plugins\Chat\MessageRole::Assistant,
                content: [$toolUseContent],
            ),
            usage: new Usage(100, 50),
            stopReason: StopReason::ToolUse,
        );

        // Second response after error
        $secondResponse = new ChatResponse(
            message: Message::assistant('I could not find that tool.'),
            usage: new Usage(80, 40),
            stopReason: StopReason::EndTurn,
        );

        $chatClient->shouldReceive('send')
            ->twice()
            ->andReturn($firstResponse, $secondResponse);

        $pluginManager->shouldReceive('applyHooks')
            ->andReturnUsing(fn ($hook, ...$args) => $args[0] ?? null);

        $loop = new SimpleAgentLoop($chatClient, $toolExecutor, $pluginManager);

        $agent = Agent::make('test-agent')
            ->withSystemPrompt('You are helpful.')
            ->withMaxIterations(10);
        // No tools registered

        $result = $loop->execute($agent, 'Call nonexistent tool', new AgentContext());

        expect($result->stopReason)->toBe(AgentStopReason::Completed);
        expect($result->iterations)->toHaveCount(2);
    });

    it('uses history from context', function () {
        $chatClient = Mockery::mock(ChatClientContract::class);
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        $response = new ChatResponse(
            message: Message::assistant('I remember our conversation.'),
            usage: new Usage(100, 50),
            stopReason: StopReason::EndTurn,
        );

        $chatClient->shouldReceive('send')
            ->once()
            ->andReturn($response);

        $pluginManager->shouldReceive('applyHooks')
            ->andReturnUsing(fn ($hook, ...$args) => $args[0] ?? null);

        $loop = new SimpleAgentLoop($chatClient, $toolExecutor, $pluginManager);

        $agent = Agent::make('context-agent')
            ->withSystemPrompt('You are helpful.')
            ->withMaxIterations(10);

        $history = MessageCollection::make()
            ->user('Hi there')
            ->assistant('Hello!');

        $context = (new AgentContext())->withHistory($history);

        $result = $loop->execute($agent, 'Do you remember?', $context);

        expect($result->stopReason)->toBe(AgentStopReason::Completed);
    });

    it('adds messages to memory when configured', function () {
        $chatClient = Mockery::mock(ChatClientContract::class);
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        $response = new ChatResponse(
            message: Message::assistant('Stored in memory.'),
            usage: new Usage(100, 50),
            stopReason: StopReason::EndTurn,
        );

        $chatClient->shouldReceive('send')
            ->once()
            ->andReturn($response);

        $pluginManager->shouldReceive('applyHooks')
            ->andReturnUsing(fn ($hook, ...$args) => $args[0] ?? null);

        $loop = new SimpleAgentLoop($chatClient, $toolExecutor, $pluginManager);

        $memory = new BufferMemory();

        $agent = Agent::make('memory-agent')
            ->withSystemPrompt('You remember things.')
            ->withMaxIterations(10)
            ->withMemory($memory);

        expect($memory->isEmpty())->toBeTrue();

        $result = $loop->execute($agent, 'Remember this', new AgentContext());

        expect($result->stopReason)->toBe(AgentStopReason::Completed);
        expect($memory->isEmpty())->toBeFalse();
        expect($memory->count())->toBeGreaterThan(0);
    });

    it('accumulates usage across iterations', function () {
        $chatClient = Mockery::mock(ChatClientContract::class);
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        // First response with tool call
        $toolUseContent = new ToolUseContent('tool_1', 'test_tool', []);
        $firstResponse = new ChatResponse(
            message: new Message(
                role: \JayI\Cortex\Plugins\Chat\MessageRole::Assistant,
                content: [$toolUseContent],
            ),
            usage: new Usage(100, 50),
            stopReason: StopReason::ToolUse,
        );

        // Second response
        $secondResponse = new ChatResponse(
            message: Message::assistant('Done.'),
            usage: new Usage(80, 40),
            stopReason: StopReason::EndTurn,
        );

        $chatClient->shouldReceive('send')
            ->twice()
            ->andReturn($firstResponse, $secondResponse);

        $toolExecutor->shouldReceive('executeTool')
            ->once()
            ->andReturn(ToolResult::success('ok'));

        $pluginManager->shouldReceive('applyHooks')
            ->andReturnUsing(fn ($hook, ...$args) => $args[0] ?? null);

        $loop = new SimpleAgentLoop($chatClient, $toolExecutor, $pluginManager);

        $tool = Tool::make('test_tool')
            ->withInput(Schema::object())
            ->withHandler(fn () => ToolResult::success('ok'));

        $agent = Agent::make('usage-agent')
            ->withSystemPrompt('Track usage.')
            ->withMaxIterations(10)
            ->addTool($tool);

        $result = $loop->execute($agent, 'Test usage', new AgentContext());

        expect($result->totalUsage->inputTokens)->toBe(180); // 100 + 80
        expect($result->totalUsage->outputTokens)->toBe(90); // 50 + 40
    });

    it('accepts array input', function () {
        $chatClient = Mockery::mock(ChatClientContract::class);
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $pluginManager = Mockery::mock(PluginManagerContract::class);

        $response = new ChatResponse(
            message: Message::assistant('Processed array input.'),
            usage: new Usage(100, 50),
            stopReason: StopReason::EndTurn,
        );

        $chatClient->shouldReceive('send')
            ->once()
            ->andReturn($response);

        $pluginManager->shouldReceive('applyHooks')
            ->andReturnUsing(fn ($hook, ...$args) => $args[0] ?? null);

        $loop = new SimpleAgentLoop($chatClient, $toolExecutor, $pluginManager);

        $agent = Agent::make('array-agent')
            ->withSystemPrompt('You process arrays.')
            ->withMaxIterations(10);

        $result = $loop->execute($agent, ['key' => 'value', 'nested' => ['a' => 1]], new AgentContext());

        expect($result->stopReason)->toBe(AgentStopReason::Completed);
    });
});
