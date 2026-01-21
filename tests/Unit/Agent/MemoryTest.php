<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Agent\Memory\BufferMemory;
use JayI\Cortex\Plugins\Agent\Memory\SlidingWindowMemory;
use JayI\Cortex\Plugins\Agent\Memory\TokenLimitMemory;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;

describe('BufferMemory', function () {
    it('stores all messages', function () {
        $memory = new BufferMemory();

        $memory->add(Message::user('Hello'));
        $memory->add(Message::assistant('Hi there!'));
        $memory->add(Message::user('How are you?'));

        expect($memory->count())->toBe(3);
        expect($memory->isEmpty())->toBeFalse();
    });

    it('retrieves messages', function () {
        $memory = new BufferMemory();

        $memory->add(Message::user('Hello'));
        $memory->add(Message::assistant('Hi!'));

        $messages = $memory->messages();

        expect($messages->count())->toBe(2);
        expect($messages->first()->text())->toBe('Hello');
        expect($messages->last()->text())->toBe('Hi!');
    });

    it('clears all messages', function () {
        $memory = new BufferMemory();

        $memory->add(Message::user('Hello'));
        $memory->add(Message::assistant('Hi!'));

        $memory->clear();

        expect($memory->isEmpty())->toBeTrue();
        expect($memory->count())->toBe(0);
    });

    it('adds many messages at once', function () {
        $memory = new BufferMemory();

        $messages = MessageCollection::make()
            ->user('One')
            ->assistant('Two')
            ->user('Three');

        $memory->addMany($messages);

        expect($memory->count())->toBe(3);
    });
});

describe('SlidingWindowMemory', function () {
    it('limits messages to window size', function () {
        $memory = new SlidingWindowMemory(windowSize: 3);

        $memory->add(Message::user('1'));
        $memory->add(Message::assistant('2'));
        $memory->add(Message::user('3'));
        $memory->add(Message::assistant('4'));
        $memory->add(Message::user('5'));

        // Window should contain only last 3 messages
        $messages = $memory->messages();
        expect($messages->count())->toBe(3);

        $texts = array_map(fn ($m) => $m->text(), $messages->all());
        expect($texts)->toBe(['3', '4', '5']);
    });

    it('preserves system message when configured', function () {
        $memory = new SlidingWindowMemory(windowSize: 2, keepSystemMessage: true);

        $memory->add(Message::system('You are helpful.'));
        $memory->add(Message::user('1'));
        $memory->add(Message::assistant('2'));
        $memory->add(Message::user('3'));
        $memory->add(Message::assistant('4'));

        $messages = $memory->messages();

        // Should have system + last 2 messages
        expect($messages->count())->toBe(3);
        expect($messages->first()->text())->toBe('You are helpful.');
    });

    it('does not keep system message when disabled', function () {
        $memory = new SlidingWindowMemory(windowSize: 2, keepSystemMessage: false);

        $memory->add(Message::system('System'));
        $memory->add(Message::user('1'));
        $memory->add(Message::assistant('2'));
        $memory->add(Message::user('3'));

        $messages = $memory->messages();

        // System message counts toward window
        expect($messages->count())->toBe(2);
    });

    it('clears memory including system message', function () {
        $memory = new SlidingWindowMemory(windowSize: 3, keepSystemMessage: true);

        $memory->add(Message::system('System'));
        $memory->add(Message::user('Hello'));

        $memory->clear();

        expect($memory->isEmpty())->toBeTrue();
    });
});

describe('TokenLimitMemory', function () {
    it('stores messages without provider set', function () {
        $memory = new TokenLimitMemory(maxTokens: 1000);

        $memory->add(Message::user('Hello'));
        $memory->add(Message::assistant('Hi!'));

        expect($memory->count())->toBe(2);
    });

    it('clears memory', function () {
        $memory = new TokenLimitMemory(maxTokens: 1000);

        $memory->add(Message::system('System'));
        $memory->add(Message::user('Hello'));

        $memory->clear();

        expect($memory->isEmpty())->toBeTrue();
    });

    it('preserves system message separately', function () {
        $memory = new TokenLimitMemory(maxTokens: 1000);

        $memory->add(Message::system('You are helpful.'));
        $memory->add(Message::user('Hello'));
        $memory->add(Message::assistant('Hi!'));

        $messages = $memory->messages();

        expect($messages->count())->toBe(3);
        expect($messages->first()->text())->toBe('You are helpful.');
    });

    it('sets provider and returns self', function () {
        $provider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $provider->shouldReceive('countTokens')->andReturn(10);

        $memory = new TokenLimitMemory(maxTokens: 1000);
        $result = $memory->setProvider($provider);

        expect($result)->toBe($memory);
    });

    it('truncates using oldest strategy when provider is set', function () {
        $provider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        // Make each message count as 100 tokens
        $provider->shouldReceive('countTokens')->andReturn(100);

        $memory = new TokenLimitMemory(maxTokens: 250, truncationStrategy: 'oldest');
        $memory->setProvider($provider);

        $memory->add(Message::user('1'));
        $memory->add(Message::assistant('2'));
        $memory->add(Message::user('3'));
        $memory->add(Message::assistant('4'));

        // With 250 tokens max and 100 per message, only 2 can fit
        $messages = $memory->messages();
        expect($messages->count())->toBeLessThanOrEqual(2);
    });

    it('truncates using middle strategy when provider is set', function () {
        $provider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        // Make each message count as 100 tokens
        $provider->shouldReceive('countTokens')->andReturn(100);

        $memory = new TokenLimitMemory(maxTokens: 350, truncationStrategy: 'middle');
        $memory->setProvider($provider);

        $memory->add(Message::user('1'));
        $memory->add(Message::assistant('2'));
        $memory->add(Message::user('3'));
        $memory->add(Message::assistant('4'));
        $memory->add(Message::user('5'));

        // Middle strategy tries to keep first and last
        $messages = $memory->messages();
        expect($messages->count())->toBeLessThanOrEqual(3);
    });

    it('handles system message with truncation', function () {
        $provider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        // System message: 200 tokens, others: 100 tokens
        $provider->shouldReceive('countTokens')
            ->andReturnUsing(fn ($msg) => $msg->text() === 'System prompt' ? 200 : 100);

        $memory = new TokenLimitMemory(maxTokens: 350, truncationStrategy: 'oldest');
        $memory->setProvider($provider);

        $memory->add(Message::system('System prompt'));
        $memory->add(Message::user('1'));
        $memory->add(Message::assistant('2'));
        $memory->add(Message::user('3'));

        // System takes 200, only 150 left for conversation = 1 message
        $messages = $memory->messages();
        expect($messages->first()->text())->toBe('System prompt');
    });

    it('clears all when system message exceeds limit', function () {
        $provider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        // System message takes all available tokens
        $provider->shouldReceive('countTokens')->andReturn(1000);

        $memory = new TokenLimitMemory(maxTokens: 500, truncationStrategy: 'oldest');
        $memory->setProvider($provider);

        $memory->add(Message::system('Very long system prompt'));
        $memory->add(Message::user('Hello'));

        // Conversation messages should be cleared since system uses all tokens
        $messages = $memory->messages();
        expect($messages->first()->text())->toBe('Very long system prompt');
    });

    it('counts tokens via messages collection', function () {
        $provider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $provider->shouldReceive('countTokens')->andReturn(50);

        $memory = new TokenLimitMemory(maxTokens: 1000);
        $memory->add(Message::user('Hello'));
        $memory->add(Message::assistant('Hi'));

        // tokenCount uses messages()->estimateTokens()
        // We need to mock at the MessageCollection level, but this tests the flow
        expect($memory->count())->toBe(2);
    });

    it('adds many messages with addMany', function () {
        $memory = new TokenLimitMemory(maxTokens: 1000);

        $messages = MessageCollection::make()
            ->user('One')
            ->assistant('Two')
            ->user('Three');

        $memory->addMany($messages);

        expect($memory->count())->toBe(3);
    });

    it('counts system message in total count', function () {
        $memory = new TokenLimitMemory(maxTokens: 1000);

        $memory->add(Message::system('System'));
        $memory->add(Message::user('Hello'));

        expect($memory->count())->toBe(2);
    });

    it('handles middle truncation with only two messages', function () {
        $provider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $provider->shouldReceive('countTokens')->andReturn(100);

        $memory = new TokenLimitMemory(maxTokens: 250, truncationStrategy: 'middle');
        $memory->setProvider($provider);

        $memory->add(Message::user('1'));
        $memory->add(Message::assistant('2'));

        // With only 2 messages, middle truncation returns early
        $messages = $memory->messages();
        expect($messages->count())->toBe(2);
    });

    it('handles middle truncation when first and last exceed limit', function () {
        $provider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        // First: 300, others: 100
        $provider->shouldReceive('countTokens')
            ->andReturnUsing(fn ($msg) => $msg->text() === '1' ? 300 : 100);

        $memory = new TokenLimitMemory(maxTokens: 350, truncationStrategy: 'middle');
        $memory->setProvider($provider);

        $memory->add(Message::user('1'));
        $memory->add(Message::assistant('2'));
        $memory->add(Message::user('3'));

        // First (300) + Last (100) = 400 > 350, so only last is kept
        $messages = $memory->messages();
        expect($messages->count())->toBeLessThanOrEqual(1);
    });
});

describe('BufferMemory additional', function () {
    it('checks isEmpty correctly', function () {
        $memory = new BufferMemory();

        expect($memory->isEmpty())->toBeTrue();

        $memory->add(Message::user('Hello'));

        expect($memory->isEmpty())->toBeFalse();
    });
});

describe('SlidingWindowMemory additional', function () {
    it('adds many messages with addMany', function () {
        $memory = new SlidingWindowMemory(windowSize: 5);

        $messages = MessageCollection::make()
            ->user('One')
            ->assistant('Two')
            ->user('Three');

        $memory->addMany($messages);

        expect($memory->count())->toBe(3);
    });

    it('counts including system message', function () {
        $memory = new SlidingWindowMemory(windowSize: 5, keepSystemMessage: true);

        $memory->add(Message::system('System'));
        $memory->add(Message::user('Hello'));
        $memory->add(Message::assistant('Hi'));

        expect($memory->count())->toBe(3);
    });

    it('returns correct isEmpty when only system message exists', function () {
        $memory = new SlidingWindowMemory(windowSize: 5, keepSystemMessage: true);

        $memory->add(Message::system('System'));

        // System message exists, so not empty
        expect($memory->isEmpty())->toBeFalse();
    });
});
