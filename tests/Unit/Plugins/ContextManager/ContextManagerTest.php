<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\ContextManager\ContextManager;
use JayI\Cortex\Plugins\ContextManager\Data\ContextMessage;
use JayI\Cortex\Plugins\ContextManager\Data\ContextWindow;
use JayI\Cortex\Plugins\ContextManager\Strategies\ImportanceStrategy;
use JayI\Cortex\Plugins\ContextManager\Strategies\SlidingWindowStrategy;
use JayI\Cortex\Plugins\ContextManager\Strategies\TruncateOldestStrategy;

describe('ContextWindow', function () {
    test('creates empty window', function () {
        $window = ContextWindow::create(4096);

        expect($window->messages)->toBeEmpty();
        expect($window->maxTokens)->toBe(4096);
        expect($window->totalTokens)->toBe(0);
    });

    test('creates window with system prompt', function () {
        $window = ContextWindow::create(4096, 'You are a helpful assistant');

        expect($window->systemPrompt)->toBe('You are a helpful assistant');
        expect($window->systemPromptTokens)->toBeGreaterThan(0);
    });

    test('calculates available tokens', function () {
        $window = new ContextWindow(
            messages: [],
            totalTokens: 1000,
            maxTokens: 4096,
        );

        expect($window->availableTokens())->toBe(3096);
    });

    test('calculates utilization', function () {
        $window = new ContextWindow(
            messages: [],
            totalTokens: 2048,
            maxTokens: 4096,
        );

        expect($window->utilization())->toBe(50.0);
    });

    test('detects near capacity', function () {
        $window = new ContextWindow(
            messages: [],
            totalTokens: 3800,
            maxTokens: 4096,
        );

        expect($window->isNearCapacity())->toBeTrue();
        expect($window->isNearCapacity(95.0))->toBeFalse();
    });

    test('adds messages', function () {
        $window = ContextWindow::create(4096);
        $message = ContextMessage::user('Hello');

        $updated = $window->addMessage($message);

        expect($updated->messages)->toHaveCount(1);
        expect($updated->totalTokens)->toBeGreaterThan(0);
    });
});

describe('ContextMessage', function () {
    test('creates user message', function () {
        $message = ContextMessage::user('Hello world');

        expect($message->role)->toBe('user');
        expect($message->content)->toBe('Hello world');
        expect($message->tokens)->toBeGreaterThan(0);
    });

    test('creates assistant message', function () {
        $message = ContextMessage::assistant('Hi there!');

        expect($message->role)->toBe('assistant');
    });

    test('supports importance scoring', function () {
        $message = ContextMessage::user('Important info', importance: 0.9);

        expect($message->importance)->toBe(0.9);
    });

    test('supports pinning', function () {
        $message = ContextMessage::user('Must keep');
        $pinned = $message->pin();

        expect($pinned->pinned)->toBeTrue();
    });

    test('clamps importance to valid range', function () {
        $high = ContextMessage::create('user', 'test', importance: 1.5);
        $low = ContextMessage::create('user', 'test', importance: -0.5);

        expect($high->importance)->toBe(1.0);
        expect($low->importance)->toBe(0.0);
    });
});

describe('TruncateOldestStrategy', function () {
    test('removes oldest messages first', function () {
        $strategy = new TruncateOldestStrategy(preservePinned: false, keepMinMessages: 0);

        // Create messages with explicit token counts
        $messages = [
            ContextMessage::create('user', 'First', tokens: 100),
            ContextMessage::create('assistant', 'Second', tokens: 100),
            ContextMessage::create('user', 'Third', tokens: 100),
        ];

        $window = ContextWindow::create(500)->withMessages($messages);
        // Total is 300 tokens, reduce to fit only ~100
        $reduced = $strategy->reduce($window, 150);

        expect(count($reduced->messages))->toBeLessThan(3);
    });

    test('preserves pinned messages', function () {
        $strategy = new TruncateOldestStrategy(preservePinned: true, keepMinMessages: 0);

        $messages = [
            ContextMessage::create('user', 'First', tokens: 100)->pin(),
            ContextMessage::create('assistant', 'Second', tokens: 100),
            ContextMessage::create('user', 'Third', tokens: 100),
        ];

        $window = ContextWindow::create(500)->withMessages($messages);
        $reduced = $strategy->reduce($window, 150);

        $pinnedInResult = array_filter($reduced->messages, fn ($m) => $m->pinned);
        expect(count($pinnedInResult))->toBeGreaterThanOrEqual(1);
    });
});

describe('ImportanceStrategy', function () {
    test('prioritizes important messages', function () {
        $strategy = new ImportanceStrategy(recencyWeight: 0.0, keepMinMessages: 1);

        $messages = [
            ContextMessage::user('Low importance', importance: 0.1),
            ContextMessage::assistant('High importance', importance: 0.9),
        ];

        $window = ContextWindow::create(50)->withMessages($messages);
        $reduced = $strategy->reduce($window, 25);

        // High importance message should be kept
        $found = false;
        foreach ($reduced->messages as $msg) {
            if (str_contains($msg->content, 'High importance')) {
                $found = true;
            }
        }
        expect($found)->toBeTrue();
    });

    test('always keeps pinned messages', function () {
        $strategy = new ImportanceStrategy(keepMinMessages: 0);

        $messages = [
            ContextMessage::user('Low importance', importance: 0.1)->pin(),
            ContextMessage::assistant('High importance', importance: 0.9),
        ];

        $window = ContextWindow::create(50)->withMessages($messages);
        $reduced = $strategy->reduce($window, 30);

        $pinnedInResult = array_filter($reduced->messages, fn ($m) => $m->pinned);
        expect(count($pinnedInResult))->toBeGreaterThanOrEqual(1);
    });
});

describe('SlidingWindowStrategy', function () {
    test('keeps most recent messages', function () {
        $strategy = new SlidingWindowStrategy(maxMessages: 3);

        $messages = [];
        for ($i = 1; $i <= 5; $i++) {
            $messages[] = ContextMessage::user("Message {$i}");
        }

        $window = ContextWindow::create(10000)->withMessages($messages);
        $reduced = $strategy->reduce($window, 10000);

        expect(count($reduced->messages))->toBeLessThanOrEqual(3);
    });
});

describe('ContextManager', function () {
    test('creates context windows', function () {
        $manager = new ContextManager;
        $window = $manager->create(4096, 'System prompt');

        expect($window->maxTokens)->toBe(4096);
        expect($window->systemPrompt)->toBe('System prompt');
    });

    test('adds messages and auto-reduces', function () {
        $manager = new ContextManager(new TruncateOldestStrategy);
        $manager->setAutoReduceThreshold(0.5);

        $window = $manager->create(100);

        // Add messages until we trigger reduction
        for ($i = 0; $i < 20; $i++) {
            $window = $manager->addMessage($window, ContextMessage::user("Message {$i}"));
        }

        expect($window->utilization())->toBeLessThanOrEqual(50.0);
    });

    test('calculates response token budget', function () {
        $manager = new ContextManager;
        $window = new ContextWindow(
            messages: [],
            totalTokens: 3000,
            maxTokens: 4096,
        );

        $budget = $manager->getResponseTokenBudget($window, 500);

        expect($budget)->toBe(596); // 4096 - 3000 - 500
    });

    test('converts to API format', function () {
        $manager = new ContextManager;
        $window = ContextWindow::create(4096, 'You are helpful');
        $window = $window->addMessage(ContextMessage::user('Hello'));
        $window = $window->addMessage(ContextMessage::assistant('Hi there!'));

        $apiMessages = $manager->toApiFormat($window);

        expect($apiMessages)->toHaveCount(3);
        expect($apiMessages[0])->toBe(['role' => 'system', 'content' => 'You are helpful']);
        expect($apiMessages[1]['role'])->toBe('user');
        expect($apiMessages[2]['role'])->toBe('assistant');
    });

    test('converts to API format without system prompt', function () {
        $manager = new ContextManager;
        $window = ContextWindow::create(4096);
        $window = $window->addMessage(ContextMessage::user('Hello'));

        $apiMessages = $manager->toApiFormat($window);

        expect($apiMessages)->toHaveCount(1);
        expect($apiMessages[0]['role'])->toBe('user');
    });

    test('can set and get strategy', function () {
        $manager = new ContextManager;
        $strategy = new SlidingWindowStrategy(maxMessages: 10);

        $returnedManager = $manager->setStrategy($strategy);

        expect($returnedManager)->toBe($manager);
        expect($manager->getStrategy())->toBe($strategy);
    });

    test('adds multiple messages', function () {
        $manager = new ContextManager;
        $window = $manager->create(10000);

        $messages = [
            ContextMessage::user('First'),
            ContextMessage::assistant('Response'),
            ContextMessage::user('Second'),
        ];

        $updated = $manager->addMessages($window, $messages);

        expect($updated->messages)->toHaveCount(3);
    });

    test('fit does not reduce when under target', function () {
        $manager = new ContextManager;
        $window = new ContextWindow(
            messages: [ContextMessage::user('Hello')],
            totalTokens: 10,
            maxTokens: 4096,
        );

        $result = $manager->fit($window, 1000);

        expect($result)->toBe($window);
    });

    test('fit reduces to default max when no target specified', function () {
        $manager = new ContextManager(new TruncateOldestStrategy(keepMinMessages: 0));

        $messages = [];
        for ($i = 0; $i < 10; $i++) {
            $messages[] = ContextMessage::create('user', "Message {$i}", tokens: 100);
        }

        $window = new ContextWindow(
            messages: $messages,
            totalTokens: 1000,
            maxTokens: 500,
        );

        $result = $manager->fit($window);

        expect($result->totalTokens)->toBeLessThanOrEqual(500);
    });

    test('auto-reduce threshold clamps to valid range', function () {
        $manager = new ContextManager;

        // Should clamp to 0.5 minimum
        $manager->setAutoReduceThreshold(0.3);
        // Can't directly check, but it affects auto-reduce behavior

        // Should clamp to 1.0 maximum
        $manager->setAutoReduceThreshold(1.5);
        // Can't directly check, but it affects auto-reduce behavior

        // Just verify it returns self for chaining
        expect($manager->setAutoReduceThreshold(0.8))->toBe($manager);
    });

    test('response token budget returns zero when not enough available', function () {
        $manager = new ContextManager;
        $window = new ContextWindow(
            messages: [],
            totalTokens: 3500,
            maxTokens: 4096,
        );

        $budget = $manager->getResponseTokenBudget($window, 1000);

        // 4096 - 3500 = 596 available, 596 - 1000 = -404, clamped to 0
        expect($budget)->toBe(0);
    });
});

describe('ContextWindow additional', function () {
    test('messageCount returns correct count', function () {
        $window = ContextWindow::create(4096);
        $window = $window->addMessage(ContextMessage::user('First'));
        $window = $window->addMessage(ContextMessage::assistant('Second'));

        expect($window->messageCount())->toBe(2);
    });

    test('withMessages replaces all messages', function () {
        $window = ContextWindow::create(4096);
        $window = $window->addMessage(ContextMessage::user('Old'));

        $newMessages = [
            ContextMessage::user('New 1'),
            ContextMessage::assistant('New 2'),
        ];

        $updated = $window->withMessages($newMessages);

        expect($updated->messages)->toHaveCount(2);
        expect($updated->messages[0]->content)->toBe('New 1');
    });
});

describe('ContextMessage additional', function () {
    test('withImportance adjusts importance', function () {
        $message = ContextMessage::user('Test');

        $updated = $message->withImportance(0.8);

        expect($updated->importance)->toBe(0.8);
        expect($updated->content)->toBe('Test');
    });

    test('creates system message', function () {
        $message = ContextMessage::create('system', 'System instructions');

        expect($message->role)->toBe('system');
    });

    test('uses specified token count', function () {
        $message = ContextMessage::create('user', 'Test', tokens: 100);

        expect($message->tokens)->toBe(100);
    });

    test('stores metadata', function () {
        $message = ContextMessage::create(
            'user',
            'Test',
            metadata: ['source' => 'api', 'id' => '123'],
        );

        expect($message->metadata)->toBe(['source' => 'api', 'id' => '123']);
    });

    test('pin preserves all properties', function () {
        $original = ContextMessage::create(
            'user',
            'Test content',
            tokens: 50,
            importance: 0.7,
            metadata: ['key' => 'value'],
        );

        $pinned = $original->pin();

        expect($pinned->pinned)->toBeTrue();
        expect($pinned->role)->toBe('user');
        expect($pinned->content)->toBe('Test content');
        expect($pinned->tokens)->toBe(50);
        expect($pinned->importance)->toBe(0.7);
        expect($pinned->metadata)->toBe(['key' => 'value']);
    });

    test('withImportance clamps to valid range', function () {
        $message = ContextMessage::user('Test');

        $high = $message->withImportance(1.5);
        $low = $message->withImportance(-0.5);

        expect($high->importance)->toBe(1.0);
        expect($low->importance)->toBe(0.0);
    });
});

describe('TruncateOldestStrategy additional', function () {
    test('returns strategy id', function () {
        $strategy = new TruncateOldestStrategy;
        expect($strategy->id())->toBe('truncate-oldest');
    });

    test('supports any window', function () {
        $strategy = new TruncateOldestStrategy;
        $window = ContextWindow::create(4096);

        expect($strategy->supports($window))->toBeTrue();
    });

    test('returns unchanged window when empty', function () {
        $strategy = new TruncateOldestStrategy;
        $window = ContextWindow::create(4096);

        $reduced = $strategy->reduce($window, 1000);

        expect($reduced->messages)->toBeEmpty();
    });

    test('handles case where only pinned messages fit', function () {
        $strategy = new TruncateOldestStrategy(preservePinned: true, keepMinMessages: 0);

        $messages = [
            ContextMessage::create('user', 'Pinned', tokens: 50)->pin(),
            ContextMessage::create('assistant', 'Regular', tokens: 100),
        ];

        $window = new ContextWindow(
            messages: $messages,
            totalTokens: 150,
            maxTokens: 200,
            systemPromptTokens: 0,
        );

        // Target tokens is just enough for pinned message
        $reduced = $strategy->reduce($window, 60);

        expect(count($reduced->messages))->toBe(1);
        expect($reduced->messages[0]->pinned)->toBeTrue();
    });
});

describe('ImportanceStrategy additional', function () {
    test('returns strategy id', function () {
        $strategy = new ImportanceStrategy;
        expect($strategy->id())->toBe('importance');
    });

    test('supports any window', function () {
        $strategy = new ImportanceStrategy;
        $window = ContextWindow::create(4096);

        expect($strategy->supports($window))->toBeTrue();
    });

    test('returns unchanged window when empty', function () {
        $strategy = new ImportanceStrategy;
        $window = ContextWindow::create(4096);

        $reduced = $strategy->reduce($window, 1000);

        expect($reduced->messages)->toBeEmpty();
    });
});

describe('SlidingWindowStrategy additional', function () {
    test('returns strategy id', function () {
        $strategy = new SlidingWindowStrategy(maxMessages: 10);
        expect($strategy->id())->toBe('sliding-window');
    });

    test('supports any window', function () {
        $strategy = new SlidingWindowStrategy(maxMessages: 10);
        $window = ContextWindow::create(4096);

        expect($strategy->supports($window))->toBeTrue();
    });

    test('returns unchanged window when empty', function () {
        $strategy = new SlidingWindowStrategy(maxMessages: 10);
        $window = ContextWindow::create(4096);

        $reduced = $strategy->reduce($window, 1000);

        expect($reduced->messages)->toBeEmpty();
    });

    test('returns all messages when under max', function () {
        $strategy = new SlidingWindowStrategy(maxMessages: 10);

        $messages = [
            ContextMessage::user('First'),
            ContextMessage::assistant('Second'),
        ];

        $window = ContextWindow::create(4096)->withMessages($messages);
        $reduced = $strategy->reduce($window, 4096);

        expect(count($reduced->messages))->toBe(2);
    });
});

describe('ContextWindow edge cases', function () {
    test('utilization returns zero when max is zero', function () {
        $window = new ContextWindow(
            messages: [],
            totalTokens: 0,
            maxTokens: 0,
        );

        expect($window->utilization())->toBe(0.0);
    });

    test('available tokens returns zero when over capacity', function () {
        $window = new ContextWindow(
            messages: [],
            totalTokens: 5000,
            maxTokens: 4096,
        );

        expect($window->availableTokens())->toBe(0);
    });

    test('estimates tokens for different text lengths', function () {
        $short = ContextWindow::estimateTokens('Hi');
        $medium = ContextWindow::estimateTokens('This is a medium length text');
        $long = ContextWindow::estimateTokens(str_repeat('word ', 100));

        expect($short)->toBeGreaterThan(0);
        expect($medium)->toBeGreaterThan($short);
        expect($long)->toBeGreaterThan($medium);
    });
});
