<?php

declare(strict_types=1);

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Events\Dispatcher;
use JayI\Cortex\Plugins\Agent\AgentResponse;
use JayI\Cortex\Plugins\Agent\AgentStopReason;
use JayI\Cortex\Plugins\Agent\Events\AgentRunCompletedEvent;
use JayI\Cortex\Plugins\Chat\Broadcasting\BroadcasterContract;
use JayI\Cortex\Plugins\Chat\Broadcasting\ChatStreamChunkEvent;
use JayI\Cortex\Plugins\Chat\Broadcasting\ChatStreamCompleteEvent;
use JayI\Cortex\Plugins\Chat\Broadcasting\EchoBroadcaster;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\StreamChunk;
use JayI\Cortex\Plugins\Chat\StreamChunkType;
use JayI\Cortex\Plugins\Chat\StreamedResponse;
use JayI\Cortex\Plugins\Chat\Usage;

describe('ChatStreamChunkEvent', function () {
    test('creates event with channel and chunk', function () {
        $chunk = StreamChunk::textDelta('Hello');
        $event = new ChatStreamChunkEvent('test-channel', $chunk);

        expect($event->channelName)->toBe('test-channel');
        expect($event->chunk)->toBe($chunk);
    });

    test('broadcasts on correct channel', function () {
        $chunk = StreamChunk::textDelta('Hello');
        $event = new ChatStreamChunkEvent('my-channel', $chunk);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(Channel::class);
        expect($channels[0]->name)->toBe('my-channel');
    });

    test('broadcasts with chunk data', function () {
        $chunk = StreamChunk::textDelta('Hello world');
        $event = new ChatStreamChunkEvent('test-channel', $chunk);

        $data = $event->broadcastWith();

        expect($data)->toHaveKey('chunk');
        expect($data['chunk']['text'])->toBe('Hello world');
        expect($data['chunk']['type'])->toBe(StreamChunkType::TextDelta->value);
    });

    test('broadcasts as correct event name', function () {
        $chunk = StreamChunk::textDelta('Hello');
        $event = new ChatStreamChunkEvent('test-channel', $chunk);

        expect($event->broadcastAs())->toBe('chat.chunk');
    });
});

describe('ChatStreamCompleteEvent', function () {
    test('creates event with channel and response', function () {
        $response = ChatResponse::fromText('Hello world');
        $event = new ChatStreamCompleteEvent('test-channel', $response);

        expect($event->channelName)->toBe('test-channel');
        expect($event->response)->toBe($response);
    });

    test('broadcasts on correct channel', function () {
        $response = ChatResponse::fromText('Hello world');
        $event = new ChatStreamCompleteEvent('my-channel', $response);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(Channel::class);
        expect($channels[0]->name)->toBe('my-channel');
    });

    test('broadcasts with response data', function () {
        $response = ChatResponse::fromText('Hello world', model: 'claude-3');
        $event = new ChatStreamCompleteEvent('test-channel', $response);

        $data = $event->broadcastWith();

        expect($data)->toHaveKey('response');
        expect($data['response']['content'])->toBe('Hello world');
        expect($data['response']['model'])->toBe('claude-3');
        expect($data['response']['stopReason'])->toBe(StopReason::EndTurn->value);
    });

    test('broadcasts as correct event name', function () {
        $response = ChatResponse::fromText('Hello');
        $event = new ChatStreamCompleteEvent('test-channel', $response);

        expect($event->broadcastAs())->toBe('chat.complete');
    });
});

describe('AgentRunCompletedEvent', function () {
    test('creates event with channel, runId and response', function () {
        $response = new AgentResponse(
            content: 'Task completed',
            messages: new MessageCollection,
            iterationCount: 1,
            iterations: [],
            totalUsage: Usage::zero(),
            stopReason: AgentStopReason::Completed,
        );
        $event = new AgentRunCompletedEvent('agent-channel', 'run-123', $response);

        expect($event->channel)->toBe('agent-channel');
        expect($event->runId)->toBe('run-123');
        expect($event->response)->toBe($response);
    });

    test('broadcasts on correct channel', function () {
        $response = new AgentResponse(
            content: 'Done',
            messages: new MessageCollection,
            iterationCount: 1,
            iterations: [],
            totalUsage: Usage::zero(),
            stopReason: AgentStopReason::Completed,
        );
        $event = new AgentRunCompletedEvent('my-agent-channel', 'run-456', $response);

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(Channel::class);
        expect($channels[0]->name)->toBe('my-agent-channel');
    });

    test('broadcasts with agent response data', function () {
        $response = new AgentResponse(
            content: 'Task completed',
            messages: new MessageCollection,
            iterationCount: 3,
            iterations: [],
            totalUsage: new Usage(100, 50, 150),
            stopReason: AgentStopReason::Completed,
        );
        $event = new AgentRunCompletedEvent('channel', 'run-789', $response);

        $data = $event->broadcastWith();

        expect($data['run_id'])->toBe('run-789');
        expect($data['content'])->toBe('Task completed');
        expect($data['iteration_count'])->toBe(3);
        expect($data['stop_reason'])->toBe('completed');
        expect($data['total_usage'])->toBeArray();
    });

    test('broadcasts as correct event name', function () {
        $response = new AgentResponse(
            content: 'Done',
            messages: new MessageCollection,
            iterationCount: 1,
            iterations: [],
            totalUsage: Usage::zero(),
            stopReason: AgentStopReason::Completed,
        );
        $event = new AgentRunCompletedEvent('channel', 'run-123', $response);

        expect($event->broadcastAs())->toBe('agent.run.completed');
    });
});

describe('EchoBroadcaster', function () {
    test('broadcasts stream chunks and returns response', function () {
        $dispatchedEvents = [];

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->andReturnUsing(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
            });

        $broadcaster = new EchoBroadcaster($dispatcher);

        // Create a mock streamed response
        $chunks = [
            StreamChunk::textDelta('Hello'),
            StreamChunk::textDelta(' world'),
            StreamChunk::messageComplete(Usage::zero(), StopReason::EndTurn),
        ];

        $streamedResponse = Mockery::mock(StreamedResponse::class);
        $streamedResponse->shouldReceive('getIterator')
            ->andReturn(new ArrayIterator($chunks));

        $finalResponse = ChatResponse::fromText('Hello world');
        $streamedResponse->shouldReceive('collect')
            ->andReturn($finalResponse);

        $result = $broadcaster->broadcast('test-channel', $streamedResponse);

        expect($result)->toBe($finalResponse);
        expect($dispatchedEvents)->toHaveCount(4); // 3 chunks + 1 complete event

        // First 3 should be chunk events
        expect($dispatchedEvents[0])->toBeInstanceOf(ChatStreamChunkEvent::class);
        expect($dispatchedEvents[1])->toBeInstanceOf(ChatStreamChunkEvent::class);
        expect($dispatchedEvents[2])->toBeInstanceOf(ChatStreamChunkEvent::class);

        // Last should be complete event
        expect($dispatchedEvents[3])->toBeInstanceOf(ChatStreamCompleteEvent::class);
    });

    test('implements BroadcasterContract', function () {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $broadcaster = new EchoBroadcaster($dispatcher);

        expect($broadcaster)->toBeInstanceOf(BroadcasterContract::class);
    });
});
