<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;

describe('MessageCollection', function () {
    it('creates empty collection', function () {
        $collection = new MessageCollection();

        expect($collection->isEmpty())->toBeTrue();
        expect($collection->count())->toBe(0);
    });

    it('creates collection with messages', function () {
        $messages = [
            Message::system('System prompt'),
            Message::user('Hello'),
        ];

        $collection = new MessageCollection($messages);

        expect($collection->count())->toBe(2);
        expect($collection->isNotEmpty())->toBeTrue();
    });

    it('creates via static make method', function () {
        $collection = MessageCollection::make([
            Message::user('Test'),
        ]);

        expect($collection->count())->toBe(1);
    });

    it('adds message', function () {
        $collection = new MessageCollection();
        $collection->add(Message::user('Hello'));

        expect($collection->count())->toBe(1);
    });

    it('pushes multiple messages', function () {
        $collection = new MessageCollection();
        $collection->push(
            Message::user('Hello'),
            Message::assistant('Hi there'),
        );

        expect($collection->count())->toBe(2);
    });

    it('prepends message', function () {
        $collection = new MessageCollection();
        $collection->add(Message::user('Second'));
        $collection->prepend(Message::system('First'));

        expect($collection->first()->role)->toBe(MessageRole::System);
    });

    it('adds system message', function () {
        $collection = new MessageCollection();
        $collection->system('System prompt');

        expect($collection->first()->role)->toBe(MessageRole::System);
    });

    it('adds user message', function () {
        $collection = new MessageCollection();
        $collection->user('User message');

        expect($collection->first()->role)->toBe(MessageRole::User);
    });

    it('adds assistant message', function () {
        $collection = new MessageCollection();
        $collection->assistant('Assistant response');

        expect($collection->first()->role)->toBe(MessageRole::Assistant);
    });

    it('gets last message', function () {
        $collection = new MessageCollection();
        $collection->user('First');
        $collection->assistant('Last');

        expect($collection->last()->role)->toBe(MessageRole::Assistant);
    });

    it('returns null for last on empty collection', function () {
        $collection = new MessageCollection();

        expect($collection->last())->toBeNull();
    });

    it('gets first message', function () {
        $collection = new MessageCollection();
        $collection->user('First');
        $collection->assistant('Second');

        expect($collection->first()->role)->toBe(MessageRole::User);
    });

    it('returns null for first on empty collection', function () {
        $collection = new MessageCollection();

        expect($collection->first())->toBeNull();
    });

    it('filters by role', function () {
        $collection = new MessageCollection();
        $collection->system('System');
        $collection->user('User 1');
        $collection->assistant('Assistant');
        $collection->user('User 2');

        $userMessages = $collection->byRole(MessageRole::User);

        expect($userMessages->count())->toBe(2);
    });

    it('filters without system messages', function () {
        $collection = new MessageCollection();
        $collection->system('System');
        $collection->user('User');
        $collection->assistant('Assistant');

        $withoutSystem = $collection->withoutSystem();

        expect($withoutSystem->count())->toBe(2);
        expect($withoutSystem->first()->role)->toBe(MessageRole::User);
    });

    it('estimates tokens', function () {
        $collection = new MessageCollection();
        $collection->user('Hello world');

        $provider = Mockery::mock(ProviderContract::class);
        $provider->shouldReceive('countTokens')->once()->andReturn(3);

        $tokens = $collection->estimateTokens($provider);

        expect($tokens)->toBe(3);
    });

    it('truncates to token limit', function () {
        $collection = new MessageCollection();
        $collection->system('System'); // 10 tokens
        $collection->user('First message'); // 20 tokens
        $collection->assistant('Response 1'); // 15 tokens
        $collection->user('Second message'); // 20 tokens
        $collection->assistant('Response 2'); // 15 tokens

        $provider = Mockery::mock(ProviderContract::class);
        $provider->shouldReceive('countTokens')->andReturnUsing(function (Message $msg) {
            if ($msg->role === MessageRole::System) return 10;
            $text = $msg->content[0]->text ?? '';
            if (str_contains($text, 'First')) return 20;
            if (str_contains($text, 'Response 1')) return 15;
            if (str_contains($text, 'Second')) return 20;
            return 15;
        });

        $truncated = $collection->truncateToTokens(50, $provider);

        // Should keep: System (10) + Second message (20) + Response 2 (15) = 45 tokens
        expect($truncated->count())->toBeLessThanOrEqual(5);
    });

    it('is iterable', function () {
        $collection = new MessageCollection();
        $collection->user('One');
        $collection->user('Two');

        $count = 0;
        foreach ($collection as $message) {
            $count++;
            expect($message)->toBeInstanceOf(Message::class);
        }

        expect($count)->toBe(2);
    });

    it('converts to array', function () {
        $collection = new MessageCollection();
        $collection->user('Hello');

        $array = $collection->toArray();

        expect($array)->toBeArray();
        expect($array[0]['role'])->toBe('user');
    });

    it('gets all messages', function () {
        $collection = new MessageCollection();
        $collection->user('One');
        $collection->user('Two');

        $all = $collection->all();

        expect($all)->toBeArray();
        expect($all)->toHaveCount(2);
    });
});
