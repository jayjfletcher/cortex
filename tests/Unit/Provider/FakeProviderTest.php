<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Provider\Providers\FakeProvider;

describe('FakeProvider', function () {
    it('returns queued responses', function () {
        $fake = FakeProvider::fake([
            'Hello!',
            'How can I help?',
        ]);

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Hi'),
        );

        $response1 = $fake->chat($request);
        $response2 = $fake->chat($request);

        expect($response1->content())->toBe('Hello!');
        expect($response2->content())->toBe('How can I help?');
    });

    it('uses response factory', function () {
        $fake = FakeProvider::fake()->respondWith(
            fn (ChatRequest $request) => 'Echo: '.$request->messages->last()?->text()
        );

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Test message'),
        );

        $response = $fake->chat($request);

        expect($response->content())->toBe('Echo: Test message');
    });

    it('creates simple text response', function () {
        $fake = FakeProvider::text('Static response');

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Anything'),
        );

        $response = $fake->chat($request);

        expect($response->content())->toBe('Static response');
    });

    it('creates tool call responses', function () {
        $fake = FakeProvider::withToolCalls([
            ['name' => 'get_weather', 'input' => ['location' => 'NYC']],
        ]);

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('What is the weather?'),
        );

        $response = $fake->chat($request);

        expect($response->hasToolCalls())->toBeTrue();
        expect($response->firstToolCall()->name)->toBe('get_weather');
        expect($response->stopReason)->toBe(StopReason::ToolUse);
    });

    it('records requests', function () {
        $fake = FakeProvider::text('Response');

        $request1 = new ChatRequest(
            messages: MessageCollection::make()->user('First'),
        );
        $request2 = new ChatRequest(
            messages: MessageCollection::make()->user('Second'),
        );

        $fake->chat($request1);
        $fake->chat($request2);

        expect($fake->recordedRequests())->toHaveCount(2);
        expect($fake->recordedRequests()[0]->messages->last()?->text())->toBe('First');
    });

    it('asserts request count', function () {
        $fake = FakeProvider::text('Response');

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Hi'),
        );

        $fake->chat($request);
        $fake->chat($request);

        $fake->assertSentCount(2);
    });

    it('asserts nothing sent', function () {
        $fake = FakeProvider::text('Response');

        $fake->assertNothingSent();
    });

    it('asserts sent with callback', function () {
        $fake = FakeProvider::text('Response');

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Find hotels'),
        );

        $fake->chat($request);

        $fake->assertSent(fn (ChatRequest $r) => str_contains($r->messages->last()?->text() ?? '', 'hotels')
        );
    });

    it('streams responses', function () {
        $fake = FakeProvider::text('Hello World');

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Hi'),
        );

        $stream = $fake->stream($request);
        $chunks = iterator_to_array($stream->text());
        $fullText = implode('', $chunks);

        expect($fullText)->toBe('Hello World');
    });

    it('resets state', function () {
        $fake = FakeProvider::text('Response');

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Hi'),
        );

        $fake->chat($request);
        expect($fake->recordedRequests())->toHaveCount(1);

        $fake->reset();
        expect($fake->recordedRequests())->toBeEmpty();
    });

    it('accepts ChatResponse objects', function () {
        $customResponse = new ChatResponse(
            message: \JayI\Cortex\Plugins\Chat\Messages\Message::assistant('Custom response'),
            usage: new Usage(100, 50),
            stopReason: StopReason::EndTurn,
            model: 'test-model',
        );

        $fake = FakeProvider::fake([$customResponse]);

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Hi'),
        );

        $response = $fake->chat($request);

        expect($response->content())->toBe('Custom response');
        expect($response->usage->inputTokens)->toBe(100);
        expect($response->model)->toBe('test-model');
    });

    it('accepts closure responses', function () {
        $fake = FakeProvider::fake([
            fn () => ChatResponse::fromText('Closure response'),
        ]);

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Hi'),
        );

        $response = $fake->chat($request);

        expect($response->content())->toBe('Closure response');
    });

    it('throws when no responses available', function () {
        $fake = FakeProvider::fake(['Only one response']);

        $request = new ChatRequest(
            messages: MessageCollection::make()->user('Hi'),
        );

        $fake->chat($request);

        expect(fn () => $fake->chat($request))
            ->toThrow(RuntimeException::class);
    });
});
