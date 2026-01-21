<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Provider\Model;

describe('ChatResponse', function () {
    it('creates with message and usage', function () {
        $message = Message::assistant('Hello');
        $usage = new Usage(100, 50);

        $response = new ChatResponse(
            message: $message,
            usage: $usage,
            stopReason: StopReason::EndTurn,
        );

        expect($response->message)->toBe($message);
        expect($response->usage)->toBe($usage);
        expect($response->stopReason)->toBe(StopReason::EndTurn);
    });

    it('creates from text', function () {
        $response = ChatResponse::fromText('Hello World');

        expect($response->content())->toBe('Hello World');
        expect($response->usage->inputTokens)->toBe(0);
        expect($response->usage->outputTokens)->toBe(0);
        expect($response->stopReason)->toBe(StopReason::EndTurn);
    });

    it('creates from text with custom usage and stop reason', function () {
        $usage = new Usage(100, 50);

        $response = ChatResponse::fromText(
            'Hello',
            $usage,
            StopReason::MaxTokens,
            'gpt-4',
        );

        expect($response->usage)->toBe($usage);
        expect($response->stopReason)->toBe(StopReason::MaxTokens);
        expect($response->model)->toBe('gpt-4');
    });

    it('gets text content', function () {
        $response = ChatResponse::fromText('Test content');

        expect($response->content())->toBe('Test content');
    });

    it('returns empty string when no text content', function () {
        $toolContent = new ToolUseContent('tool_1', 'test', []);
        $message = new Message(
            role: MessageRole::Assistant,
            content: [$toolContent],
        );

        $response = new ChatResponse(
            message: $message,
            usage: Usage::zero(),
            stopReason: StopReason::ToolUse,
        );

        expect($response->content())->toBe('');
    });

    it('gets tool calls', function () {
        $toolContent = new ToolUseContent('tool_1', 'test_tool', ['arg' => 'value']);
        $message = new Message(
            role: MessageRole::Assistant,
            content: [$toolContent],
        );

        $response = new ChatResponse(
            message: $message,
            usage: Usage::zero(),
            stopReason: StopReason::ToolUse,
        );

        expect($response->toolCalls())->toHaveCount(1);
        expect($response->toolCalls()[0]->name)->toBe('test_tool');
    });

    it('checks for tool calls', function () {
        $toolContent = new ToolUseContent('tool_1', 'test', []);
        $message = new Message(
            role: MessageRole::Assistant,
            content: [$toolContent],
        );

        $response = new ChatResponse(
            message: $message,
            usage: Usage::zero(),
            stopReason: StopReason::ToolUse,
        );

        expect($response->hasToolCalls())->toBeTrue();
    });

    it('returns false when no tool calls', function () {
        $response = ChatResponse::fromText('No tools');

        expect($response->hasToolCalls())->toBeFalse();
    });

    it('gets first tool call', function () {
        $tool1 = new ToolUseContent('tool_1', 'first', []);
        $tool2 = new ToolUseContent('tool_2', 'second', []);
        $message = new Message(
            role: MessageRole::Assistant,
            content: [$tool1, $tool2],
        );

        $response = new ChatResponse(
            message: $message,
            usage: Usage::zero(),
            stopReason: StopReason::ToolUse,
        );

        expect($response->firstToolCall()->name)->toBe('first');
    });

    it('returns null when no first tool call', function () {
        $response = ChatResponse::fromText('No tools');

        expect($response->firstToolCall())->toBeNull();
    });

    it('checks if complete', function () {
        $response = new ChatResponse(
            message: Message::assistant('Done'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        expect($response->isComplete())->toBeTrue();
        expect($response->isTruncated())->toBeFalse();
        expect($response->requiresToolExecution())->toBeFalse();
    });

    it('checks if truncated', function () {
        $response = new ChatResponse(
            message: Message::assistant('Cut off'),
            usage: Usage::zero(),
            stopReason: StopReason::MaxTokens,
        );

        expect($response->isTruncated())->toBeTrue();
        expect($response->isComplete())->toBeFalse();
    });

    it('checks if requires tool execution', function () {
        $toolContent = new ToolUseContent('tool_1', 'test', []);
        $message = new Message(
            role: MessageRole::Assistant,
            content: [$toolContent],
        );

        $response = new ChatResponse(
            message: $message,
            usage: Usage::zero(),
            stopReason: StopReason::ToolUse,
        );

        expect($response->requiresToolExecution())->toBeTrue();
        expect($response->isComplete())->toBeFalse();
    });

    it('stores metadata', function () {
        $response = new ChatResponse(
            message: Message::assistant('Test'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
            metadata: ['request_id' => 'abc123'],
        );

        expect($response->metadata)->toBe(['request_id' => 'abc123']);
    });
});

describe('Usage', function () {
    it('creates with tokens', function () {
        $usage = new Usage(100, 50);

        expect($usage->inputTokens)->toBe(100);
        expect($usage->outputTokens)->toBe(50);
    });

    it('creates zero usage', function () {
        $usage = Usage::zero();

        expect($usage->inputTokens)->toBe(0);
        expect($usage->outputTokens)->toBe(0);
    });

    it('calculates total tokens', function () {
        $usage = new Usage(100, 50);

        expect($usage->totalTokens())->toBe(150);
    });

    it('adds usage together', function () {
        $usage1 = new Usage(100, 50);
        $usage2 = new Usage(200, 100);

        $combined = $usage1->add($usage2);

        expect($combined->inputTokens)->toBe(300);
        expect($combined->outputTokens)->toBe(150);
    });

    it('stores cache tokens', function () {
        $usage = new Usage(
            inputTokens: 100,
            outputTokens: 50,
            cacheReadTokens: 20,
            cacheWriteTokens: 10,
        );

        expect($usage->cacheReadTokens)->toBe(20);
        expect($usage->cacheWriteTokens)->toBe(10);
    });

    it('adds cache tokens when present', function () {
        $usage1 = new Usage(100, 50, 10, 5);
        $usage2 = new Usage(100, 50, 20, 15);

        $combined = $usage1->add($usage2);

        expect($combined->cacheReadTokens)->toBe(30);
        expect($combined->cacheWriteTokens)->toBe(20);
    });

    it('handles null cache tokens in addition', function () {
        $usage1 = new Usage(100, 50);
        $usage2 = new Usage(100, 50, 20, null);

        $combined = $usage1->add($usage2);

        expect($combined->cacheReadTokens)->toBe(20);
        expect($combined->cacheWriteTokens)->toBeNull();
    });

    it('estimates cost with model', function () {
        $usage = new Usage(1000, 500);

        $model = new Model(
            id: 'test-model',
            name: 'Test Model',
            provider: 'test',
            contextWindow: 8192,
            maxOutputTokens: 4096,
            inputCostPer1kTokens: 0.01,
            outputCostPer1kTokens: 0.03,
        );

        $cost = $usage->estimateCost($model);

        // 1000 / 1000 * 0.01 + 500 / 1000 * 0.03 = 0.01 + 0.015 = 0.025
        expect($cost)->toBe(0.025);
    });
});

describe('StopReason', function () {
    it('end turn is complete', function () {
        expect(StopReason::EndTurn->isComplete())->toBeTrue();
        expect(StopReason::EndTurn->isTruncated())->toBeFalse();
        expect(StopReason::EndTurn->requiresToolExecution())->toBeFalse();
    });

    it('max tokens is truncated', function () {
        expect(StopReason::MaxTokens->isComplete())->toBeFalse();
        expect(StopReason::MaxTokens->isTruncated())->toBeTrue();
        expect(StopReason::MaxTokens->requiresToolExecution())->toBeFalse();
    });

    it('tool use requires execution', function () {
        expect(StopReason::ToolUse->isComplete())->toBeFalse();
        expect(StopReason::ToolUse->isTruncated())->toBeFalse();
        expect(StopReason::ToolUse->requiresToolExecution())->toBeTrue();
    });

    it('stop sequence is not complete', function () {
        expect(StopReason::StopSequence->isComplete())->toBeFalse();
    });

    it('content filtered is not complete', function () {
        expect(StopReason::ContentFiltered->isComplete())->toBeFalse();
    });

    it('has correct string values', function () {
        expect(StopReason::EndTurn->value)->toBe('end_turn');
        expect(StopReason::MaxTokens->value)->toBe('max_tokens');
        expect(StopReason::StopSequence->value)->toBe('stop_sequence');
        expect(StopReason::ToolUse->value)->toBe('tool_use');
        expect(StopReason::ContentFiltered->value)->toBe('content_filtered');
    });
});
