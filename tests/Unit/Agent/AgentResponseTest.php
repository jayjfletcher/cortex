<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Agent\AgentIteration;
use JayI\Cortex\Plugins\Agent\AgentResponse;
use JayI\Cortex\Plugins\Agent\AgentStopReason;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\Usage;

describe('AgentResponse', function () {
    it('creates a successful response', function () {
        $messages = MessageCollection::make()->user('Hello');
        $iterations = [createIteration(0)];

        $response = AgentResponse::success(
            content: 'Done!',
            messages: $messages,
            iterations: $iterations,
            totalUsage: new Usage(inputTokens: 100, outputTokens: 50),
        );

        expect($response->isComplete())->toBeTrue();
        expect($response->hitMaxIterations())->toBeFalse();
        expect($response->stopReason)->toBe(AgentStopReason::Completed);
        expect($response->content)->toBe('Done!');
        expect($response->iterationCount)->toBe(1);
    });

    it('creates max iterations response', function () {
        $messages = MessageCollection::make();
        $iterations = [createIteration(0), createIteration(1), createIteration(2)];

        $response = AgentResponse::maxIterations(
            content: 'Stopped at max',
            messages: $messages,
            iterations: $iterations,
            totalUsage: Usage::zero(),
        );

        expect($response->isComplete())->toBeFalse();
        expect($response->hitMaxIterations())->toBeTrue();
        expect($response->stopReason)->toBe(AgentStopReason::MaxIterations);
        expect($response->iterationCount)->toBe(3);
    });

    it('creates tool stopped response', function () {
        $response = AgentResponse::toolStopped(
            content: 'Tool signaled stop',
            messages: MessageCollection::make(),
            iterations: [createIteration(0)],
            totalUsage: Usage::zero(),
        );

        expect($response->stopReason)->toBe(AgentStopReason::ToolStopped);
    });

    it('gets last iteration', function () {
        $iterations = [createIteration(0), createIteration(1), createIteration(2)];

        $response = AgentResponse::success(
            content: 'Done',
            messages: MessageCollection::make(),
            iterations: $iterations,
            totalUsage: Usage::zero(),
        );

        $last = $response->lastIteration();
        expect($last)->not->toBeNull();
        expect($last->index)->toBe(2);
    });

    it('collects all tool calls', function () {
        $iteration1 = new AgentIteration(
            index: 0,
            response: createChatResponse(),
            toolCalls: [
                ['tool' => 'tool1', 'input' => ['a' => 1], 'output' => 'result1'],
            ],
            usage: Usage::zero(),
            duration: 0.1,
        );

        $iteration2 = new AgentIteration(
            index: 1,
            response: createChatResponse(),
            toolCalls: [
                ['tool' => 'tool2', 'input' => ['b' => 2], 'output' => 'result2'],
                ['tool' => 'tool3', 'input' => ['c' => 3], 'output' => 'result3'],
            ],
            usage: Usage::zero(),
            duration: 0.2,
        );

        $response = AgentResponse::success(
            content: 'Done',
            messages: MessageCollection::make(),
            iterations: [$iteration1, $iteration2],
            totalUsage: Usage::zero(),
        );

        $toolCalls = $response->toolCalls();
        expect($toolCalls)->toHaveCount(3);
        expect($toolCalls[0]['tool'])->toBe('tool1');
        expect($toolCalls[1]['tool'])->toBe('tool2');
        expect($toolCalls[2]['tool'])->toBe('tool3');
    });
});

describe('AgentIteration', function () {
    it('creates an iteration', function () {
        $chatResponse = createChatResponse();

        $iteration = new AgentIteration(
            index: 0,
            response: $chatResponse,
            toolCalls: [],
            usage: new Usage(inputTokens: 10, outputTokens: 20),
            duration: 0.5,
        );

        expect($iteration->index)->toBe(0);
        expect($iteration->hasToolCalls())->toBeFalse();
        expect($iteration->usage->totalTokens())->toBe(30);
        expect($iteration->duration)->toBe(0.5);
    });

    it('detects tool calls', function () {
        $iteration = new AgentIteration(
            index: 0,
            response: createChatResponse(),
            toolCalls: [
                ['tool' => 'test', 'input' => [], 'output' => 'result'],
            ],
            usage: Usage::zero(),
            duration: 0.1,
        );

        expect($iteration->hasToolCalls())->toBeTrue();
    });

    it('gets content from response', function () {
        $chatResponse = new ChatResponse(
            message: Message::assistant('Hello from iteration'),
            usage: Usage::zero(),
            stopReason: StopReason::EndTurn,
        );

        $iteration = new AgentIteration(
            index: 0,
            response: $chatResponse,
            toolCalls: [],
            usage: Usage::zero(),
            duration: 0.1,
        );

        expect($iteration->content())->toBe('Hello from iteration');
    });
});

function createIteration(int $index): AgentIteration
{
    return new AgentIteration(
        index: $index,
        response: createChatResponse(),
        toolCalls: [],
        usage: Usage::zero(),
        duration: 0.1,
    );
}

function createChatResponse(): ChatResponse
{
    return new ChatResponse(
        message: Message::assistant('Response'),
        usage: Usage::zero(),
        stopReason: StopReason::EndTurn,
    );
}
