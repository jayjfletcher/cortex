<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentRunStatus;
use JayI\Cortex\Plugins\Agent\Contracts\RetrieverContract;
use JayI\Cortex\Plugins\Agent\RetrievedContent;
use JayI\Cortex\Plugins\Agent\RetrievedItem;
use JayI\Cortex\Plugins\Agent\Retrievers\CallbackRetriever;
use JayI\Cortex\Plugins\Agent\Retrievers\CollectionRetriever;
use JayI\Cortex\Plugins\Agent\Retrievers\EloquentRetriever;

describe('AgentRunStatus Enum', function () {
    it('has pending status', function () {
        expect(AgentRunStatus::Pending->value)->toBe('pending');
    });

    it('has running status', function () {
        expect(AgentRunStatus::Running->value)->toBe('running');
    });

    it('has completed status', function () {
        expect(AgentRunStatus::Completed->value)->toBe('completed');
    });

    it('has failed status', function () {
        expect(AgentRunStatus::Failed->value)->toBe('failed');
    });

    it('checks terminal states', function () {
        expect(AgentRunStatus::Pending->isTerminal())->toBeFalse();
        expect(AgentRunStatus::Running->isTerminal())->toBeFalse();
        expect(AgentRunStatus::Completed->isTerminal())->toBeTrue();
        expect(AgentRunStatus::Failed->isTerminal())->toBeTrue();
    });
});

describe('RetrievedItem', function () {
    it('creates item with content and score', function () {
        $item = new RetrievedItem(
            content: 'Test content',
            score: 0.95,
        );

        expect($item->content)->toBe('Test content');
        expect($item->score)->toBe(0.95);
    });

    it('creates item with metadata', function () {
        $item = new RetrievedItem(
            content: 'Test content',
            score: 0.95,
            metadata: ['source' => 'document.pdf', 'page' => 5],
        );

        expect($item->metadata['source'])->toBe('document.pdf');
        expect($item->metadata['page'])->toBe(5);
    });

    it('converts to context string', function () {
        $item = new RetrievedItem(
            content: 'Important information here',
            score: 0.9,
        );

        $context = $item->toContext();

        expect($context)->toContain('Important information here');
    });

    it('creates from array', function () {
        $item = RetrievedItem::fromArray([
            'content' => 'Test content',
            'score' => 0.85,
            'metadata' => ['key' => 'value'],
        ]);

        expect($item->content)->toBe('Test content');
        expect($item->score)->toBe(0.85);
    });
});

describe('RetrievedContent', function () {
    it('creates with items', function () {
        $items = [
            new RetrievedItem('Content 1', 0.9),
            new RetrievedItem('Content 2', 0.8),
        ];

        $content = new RetrievedContent($items);

        expect($content->items)->toHaveCount(2);
    });

    it('checks if empty', function () {
        $empty = new RetrievedContent([]);
        $notEmpty = new RetrievedContent([new RetrievedItem('Content', 0.9)]);

        expect($empty->isEmpty())->toBeTrue();
        expect($notEmpty->isEmpty())->toBeFalse();
        expect($notEmpty->isNotEmpty())->toBeTrue();
    });

    it('counts items', function () {
        $items = [
            new RetrievedItem('Content 1', 0.9),
            new RetrievedItem('Content 2', 0.8),
            new RetrievedItem('Content 3', 0.7),
        ];

        $content = new RetrievedContent($items);

        expect($content->count())->toBe(3);
    });

    it('sorts by score', function () {
        $items = [
            new RetrievedItem('Low', 0.5),
            new RetrievedItem('High', 0.9),
            new RetrievedItem('Medium', 0.7),
        ];

        $content = new RetrievedContent($items);
        $sorted = $content->sortedByScore();

        expect($sorted[0]->content)->toBe('High');
        expect($sorted[1]->content)->toBe('Medium');
        expect($sorted[2]->content)->toBe('Low');
    });

    it('takes first n items', function () {
        $items = [
            new RetrievedItem('Content 1', 0.9),
            new RetrievedItem('Content 2', 0.8),
            new RetrievedItem('Content 3', 0.7),
        ];

        $content = new RetrievedContent($items);
        $taken = $content->take(2);

        expect($taken)->toHaveCount(2);
    });

    it('filters by minimum score', function () {
        $items = [
            new RetrievedItem('High', 0.9),
            new RetrievedItem('Medium', 0.7),
            new RetrievedItem('Low', 0.3),
        ];

        $content = new RetrievedContent($items);
        $filtered = $content->filterByScore(0.5);

        expect($filtered->count())->toBe(2);
    });

    it('converts to context string', function () {
        $items = [
            new RetrievedItem('First item', 0.9),
            new RetrievedItem('Second item', 0.8),
        ];

        $content = new RetrievedContent($items);
        $context = $content->toContext();

        expect($context)->toContain('First item');
        expect($context)->toContain('Second item');
    });

    it('converts to string array', function () {
        $items = [
            new RetrievedItem('First', 0.9),
            new RetrievedItem('Second', 0.8),
        ];

        $content = new RetrievedContent($items);
        $strings = $content->toStringArray();

        expect($strings)->toBe(['First', 'Second']);
    });

    it('creates from array', function () {
        $content = RetrievedContent::fromItems([
            ['content' => 'Item 1', 'score' => 0.9],
            ['content' => 'Item 2', 'score' => 0.8],
        ]);

        expect($content->count())->toBe(2);
    });
});

describe('CallbackRetriever', function () {
    it('retrieves using callback', function () {
        $retriever = CallbackRetriever::make(function (string $query, int $limit) {
            return new RetrievedContent([
                new RetrievedItem("Result for: {$query}", 1.0),
            ]);
        });

        $result = $retriever->retrieve('test query', 5);

        expect($result->items[0]->content)->toBe('Result for: test query');
    });

    it('implements RetrieverContract', function () {
        $retriever = CallbackRetriever::make(fn () => new RetrievedContent([]));

        expect($retriever)->toBeInstanceOf(RetrieverContract::class);
    });
});

describe('CollectionRetriever', function () {
    it('searches collection with text matching', function () {
        $items = collect([
            ['content' => 'PHP is a programming language'],
            ['content' => 'Python is also a programming language'],
            ['content' => 'JavaScript runs in browsers'],
        ]);

        $retriever = CollectionRetriever::make($items);
        $result = $retriever->retrieve('programming language', 5);

        expect($result->count())->toBeGreaterThan(0);
    });

    it('respects limit', function () {
        $items = collect([
            ['content' => 'Item 1 matches query'],
            ['content' => 'Item 2 matches query'],
            ['content' => 'Item 3 matches query'],
        ]);

        $retriever = CollectionRetriever::make($items);
        $result = $retriever->retrieve('query', 2);

        expect($result->count())->toBeLessThanOrEqual(2);
    });

    it('returns empty for no matches', function () {
        $items = collect([
            ['content' => 'Unrelated content'],
        ]);

        $retriever = CollectionRetriever::make($items);
        $result = $retriever->retrieve('xyz123', 5);

        expect($result->isEmpty())->toBeTrue();
    });

    it('creates from strings', function () {
        $retriever = CollectionRetriever::fromStrings([
            'First document about cats',
            'Second document about dogs',
            'Third document about birds',
        ]);

        $result = $retriever->retrieve('cats', 5);

        expect($result->count())->toBeGreaterThan(0);
    });

    it('uses custom content key', function () {
        $items = collect([
            ['text' => 'Custom key content about testing'],
        ]);

        $retriever = CollectionRetriever::make($items, contentKey: 'text');
        $result = $retriever->retrieve('testing', 5);

        expect($result->count())->toBeGreaterThan(0);
    });

    it('implements RetrieverContract', function () {
        $retriever = CollectionRetriever::make(collect([]));

        expect($retriever)->toBeInstanceOf(RetrieverContract::class);
    });
});

describe('EloquentRetriever', function () {
    it('creates retriever for model', function () {
        // Note: This is a unit test that doesn't connect to database
        $retriever = EloquentRetriever::make(
            model: 'App\\Models\\Document',
            searchColumns: ['title', 'content']
        );

        expect($retriever)->toBeInstanceOf(EloquentRetriever::class);
        expect($retriever)->toBeInstanceOf(RetrieverContract::class);
    });

    it('configures content column', function () {
        $retriever = EloquentRetriever::make('App\\Models\\Document', ['content'])
            ->contentColumn('body');

        expect($retriever)->toBeInstanceOf(EloquentRetriever::class);
    });

    it('configures score column', function () {
        $retriever = EloquentRetriever::make('App\\Models\\Document', ['content'])
            ->scoreColumn('relevance_score');

        expect($retriever)->toBeInstanceOf(EloquentRetriever::class);
    });

    it('accepts query modifier', function () {
        $retriever = EloquentRetriever::make('App\\Models\\Document', ['content'])
            ->modifyQuery(function ($query, $searchQuery) {
                $query->where('published', true);
            });

        expect($retriever)->toBeInstanceOf(EloquentRetriever::class);
    });
});

describe('Agent with Retriever', function () {
    it('configures retriever', function () {
        $retriever = CollectionRetriever::fromStrings(['Test content']);

        $agent = Agent::make('test-agent')
            ->withRetriever($retriever, limit: 10);

        expect($agent->retriever())->toBe($retriever);
    });

    it('accepts retriever with default limit', function () {
        $retriever = CollectionRetriever::fromStrings(['Test content']);

        $agent = Agent::make('test-agent')
            ->withRetriever($retriever);

        expect($agent->retriever())->toBe($retriever);
    });
});
