<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Chat\ToolCollection;

describe('ToolCollection', function () {
    it('creates empty collection', function () {
        $collection = new ToolCollection();

        expect($collection->count())->toBe(0);
    });

    it('creates collection with tools', function () {
        $tool1 = new class {
            public function name(): string { return 'search'; }
        };
        $tool2 = new class {
            public function name(): string { return 'calculate'; }
        };

        $collection = new ToolCollection([$tool1, $tool2]);

        expect($collection->count())->toBe(2);
    });

    it('creates collection with array tools', function () {
        $collection = new ToolCollection([
            ['name' => 'search', 'description' => 'Search tool'],
            ['name' => 'calculate', 'description' => 'Calculator'],
        ]);

        expect($collection->count())->toBe(2);
    });

    it('creates via static make method', function () {
        $tool = new class {
            public function name(): string { return 'test'; }
        };

        $collection = ToolCollection::make([$tool]);

        expect($collection->count())->toBe(1);
    });

    it('adds object tool', function () {
        $tool = new class {
            public function name(): string { return 'test'; }
        };

        $collection = new ToolCollection();
        $collection->add($tool);

        expect($collection->has('test'))->toBeTrue();
    });

    it('adds array tool', function () {
        $collection = new ToolCollection();
        $collection->add(['name' => 'search', 'description' => 'Search']);

        expect($collection->has('search'))->toBeTrue();
    });

    it('removes tool', function () {
        $tool = new class {
            public function name(): string { return 'test'; }
        };

        $collection = new ToolCollection([$tool]);
        $collection->remove('test');

        expect($collection->has('test'))->toBeFalse();
    });

    it('gets tool by name', function () {
        $tool = new class {
            public function name(): string { return 'search'; }
            public function value(): string { return 'search-tool'; }
        };

        $collection = new ToolCollection([$tool]);

        expect($collection->get('search'))->toBe($tool);
    });

    it('returns null for missing tool', function () {
        $collection = new ToolCollection();

        expect($collection->get('nonexistent'))->toBeNull();
    });

    it('checks if tool exists', function () {
        $tool = new class {
            public function name(): string { return 'search'; }
        };

        $collection = new ToolCollection([$tool]);

        expect($collection->has('search'))->toBeTrue();
        expect($collection->has('nonexistent'))->toBeFalse();
    });

    it('gets tool names', function () {
        $tool1 = new class {
            public function name(): string { return 'search'; }
        };
        $tool2 = new class {
            public function name(): string { return 'calculate'; }
        };

        $collection = new ToolCollection([$tool1, $tool2]);

        expect($collection->names())->toBe(['search', 'calculate']);
    });

    it('converts to tool definitions', function () {
        $tool = new class {
            public function name(): string { return 'search'; }
            public function toDefinition(): array {
                return ['name' => 'search', 'description' => 'Search tool'];
            }
        };

        $collection = new ToolCollection([$tool]);
        $definitions = $collection->toToolDefinitions();

        expect($definitions)->toHaveCount(1);
        expect($definitions[0]['name'])->toBe('search');
    });

    it('converts array tools to definitions', function () {
        $collection = new ToolCollection([
            ['name' => 'search', 'description' => 'Search tool'],
        ]);
        $definitions = $collection->toToolDefinitions();

        expect($definitions)->toHaveCount(1);
        expect($definitions[0]['name'])->toBe('search');
    });

    it('is iterable', function () {
        $tool = new class {
            public function name(): string { return 'test'; }
        };

        $collection = new ToolCollection([$tool]);

        $count = 0;
        foreach ($collection as $name => $t) {
            $count++;
            expect($name)->toBe('test');
        }

        expect($count)->toBe(1);
    });

    it('converts to array', function () {
        $tool = new class {
            public function name(): string { return 'test'; }
        };

        $collection = new ToolCollection([$tool]);

        expect($collection->toArray())->toBeArray();
        expect($collection->toArray())->toHaveKey('test');
    });

    it('merges collections', function () {
        $tool1 = new class {
            public function name(): string { return 'first'; }
        };
        $tool2 = new class {
            public function name(): string { return 'second'; }
        };

        $collection1 = new ToolCollection([$tool1]);
        $collection2 = new ToolCollection([$tool2]);

        $merged = $collection1->merge($collection2);

        expect($merged->count())->toBe(2);
        expect($merged->has('first'))->toBeTrue();
        expect($merged->has('second'))->toBeTrue();
        // Original should be unchanged
        expect($collection1->count())->toBe(1);
    });
});
