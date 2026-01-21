<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolResult;

describe('ToolCollection', function () {
    beforeEach(function () {
        $this->weatherTool = Tool::make('get_weather')
            ->withDescription('Get weather')
            ->withInput(Schema::object()->property('location', Schema::string()))
            ->withHandler(fn () => ToolResult::success(['temp' => 22]));

        $this->searchTool = Tool::make('search')
            ->withDescription('Search database')
            ->withInput(Schema::object()->property('query', Schema::string()))
            ->withHandler(fn ($input) => ToolResult::success(['results' => [$input['query']]]));
    });

    it('creates an empty collection', function () {
        $collection = ToolCollection::make();

        expect($collection->isEmpty())->toBeTrue();
        expect($collection->count())->toBe(0);
    });

    it('adds tools to collection', function () {
        $collection = ToolCollection::make()
            ->add($this->weatherTool)
            ->add($this->searchTool);

        expect($collection->count())->toBe(2);
        expect($collection->has('get_weather'))->toBeTrue();
        expect($collection->has('search'))->toBeTrue();
    });

    it('gets tool by name', function () {
        $collection = ToolCollection::make([$this->weatherTool]);

        $tool = $collection->get('get_weather');
        expect($tool)->toBe($this->weatherTool);

        expect($collection->get('nonexistent'))->toBeNull();
    });

    it('removes tools', function () {
        $collection = ToolCollection::make([$this->weatherTool, $this->searchTool]);
        $collection->remove('get_weather');

        expect($collection->has('get_weather'))->toBeFalse();
        expect($collection->has('search'))->toBeTrue();
        expect($collection->count())->toBe(1);
    });

    it('returns tool names', function () {
        $collection = ToolCollection::make([$this->weatherTool, $this->searchTool]);

        expect($collection->names())->toBe(['get_weather', 'search']);
    });

    it('converts to tool definitions', function () {
        $collection = ToolCollection::make([$this->weatherTool, $this->searchTool]);
        $definitions = $collection->toToolDefinitions();

        expect($definitions)->toHaveCount(2);
        expect($definitions[0]['name'])->toBe('get_weather');
        expect($definitions[1]['name'])->toBe('search');
    });

    it('is iterable', function () {
        $collection = ToolCollection::make([$this->weatherTool, $this->searchTool]);

        $names = [];
        foreach ($collection as $name => $tool) {
            $names[] = $name;
        }

        expect($names)->toBe(['get_weather', 'search']);
    });

    it('merges collections', function () {
        $collection1 = ToolCollection::make([$this->weatherTool]);
        $collection2 = ToolCollection::make([$this->searchTool]);

        $merged = $collection1->merge($collection2);

        expect($merged->count())->toBe(2);
        expect($merged->has('get_weather'))->toBeTrue();
        expect($merged->has('search'))->toBeTrue();
    });

    it('filters tools', function () {
        $collection = ToolCollection::make([$this->weatherTool, $this->searchTool]);

        $filtered = $collection->filter(fn ($tool) => $tool->name() === 'search');

        expect($filtered->count())->toBe(1);
        expect($filtered->has('search'))->toBeTrue();
    });

    it('executes a tool by name', function () {
        $collection = ToolCollection::make([$this->weatherTool, $this->searchTool]);

        $result = $collection->execute('search', ['query' => 'test']);

        expect($result->success)->toBeTrue();
        expect($result->output['results'])->toBe(['test']);
    });

    it('returns error when executing nonexistent tool', function () {
        $collection = ToolCollection::make();

        $result = $collection->execute('missing', []);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('not found');
    });
});
