<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract;
use JayI\Cortex\Plugins\Mcp\McpServerCollection;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use JayI\Cortex\Plugins\Tool\ToolResult;

describe('McpServerCollection', function () {
    it('creates an empty collection', function () {
        $collection = McpServerCollection::make([]);

        expect($collection->count())->toBe(0);
        expect($collection->isEmpty())->toBeTrue();
        expect($collection->isNotEmpty())->toBeFalse();
    });

    it('creates collection with server instances', function () {
        $server1 = Mockery::mock(McpServerContract::class);
        $server1->shouldReceive('id')->andReturn('server-1');

        $server2 = Mockery::mock(McpServerContract::class);
        $server2->shouldReceive('id')->andReturn('server-2');

        $collection = McpServerCollection::make([$server1, $server2]);

        expect($collection->count())->toBe(2);
        expect($collection->has('server-1'))->toBeTrue();
        expect($collection->has('server-2'))->toBeTrue();
    });

    it('creates collection with string references', function () {
        $collection = McpServerCollection::make(['my-server', 'App\\Mcp\\CustomServer']);

        expect($collection->count())->toBe(2);
        expect($collection->has('my-server'))->toBeTrue();
        expect($collection->has('App\\Mcp\\CustomServer'))->toBeTrue();
    });

    it('creates collection with mixed array', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'registry-entry', 'App\\Mcp\\Server']);

        expect($collection->count())->toBe(3);
        expect($collection->has('server-1'))->toBeTrue();
        expect($collection->has('registry-entry'))->toBeTrue();
        expect($collection->has('App\\Mcp\\Server'))->toBeTrue();
    });

    it('adds servers to collection', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('added-server');

        $collection = McpServerCollection::make([]);
        $collection = $collection->add($server);
        $collection = $collection->add('string-server');

        expect($collection->count())->toBe(2);
        expect($collection->has('added-server'))->toBeTrue();
        expect($collection->has('string-server'))->toBeTrue();
    });

    it('removes servers from collection', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);
        $collection = $collection->remove('server-1');

        expect($collection->count())->toBe(1);
        expect($collection->has('server-1'))->toBeFalse();
        expect($collection->has('string-server'))->toBeTrue();
    });

    it('gets server by id', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);

        expect($collection->get('server-1'))->toBe($server);
        expect($collection->get('string-server'))->toBe('string-server');
        expect($collection->get('nonexistent'))->toBeNull();
    });

    it('returns server ids', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);

        $ids = $collection->ids();

        expect($ids)->toContain('server-1');
        expect($ids)->toContain('string-server');
    });

    it('returns only resolved servers', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);

        $resolved = $collection->resolved();

        expect($resolved)->toHaveCount(1);
        expect($resolved['server-1'])->toBe($server);
    });

    it('returns only unresolved servers', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server', 'another-string']);

        $unresolved = $collection->unresolved();

        expect($unresolved)->toHaveCount(2);
        expect($unresolved)->toContain('string-server');
        expect($unresolved)->toContain('another-string');
    });

    it('merges collections', function () {
        $server1 = Mockery::mock(McpServerContract::class);
        $server1->shouldReceive('id')->andReturn('server-1');

        $server2 = Mockery::mock(McpServerContract::class);
        $server2->shouldReceive('id')->andReturn('server-2');

        $collection1 = McpServerCollection::make([$server1]);
        $collection2 = McpServerCollection::make([$server2, 'string-server']);

        $merged = $collection1->merge($collection2);

        expect($merged->count())->toBe(3);
        expect($merged->has('server-1'))->toBeTrue();
        expect($merged->has('server-2'))->toBeTrue();
        expect($merged->has('string-server'))->toBeTrue();
    });

    it('converts to array', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);

        $array = $collection->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveCount(2);
    });

    it('is iterable', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);

        $count = 0;
        foreach ($collection as $id => $server) {
            $count++;
        }

        expect($count)->toBe(2);
    });

    it('filters servers', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);

        $filtered = $collection->filter(fn ($s) => $s instanceof McpServerContract);

        expect($filtered->count())->toBe(1);
        expect($filtered->has('server-1'))->toBeTrue();
    });

    it('maps servers', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);

        $mapped = $collection->map(fn ($s) => is_string($s) ? $s : 'object');

        expect($mapped)->toBe([
            'server-1' => 'object',
            'string-server' => 'string-server',
        ]);
    });

    it('returns all servers', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);

        $all = $collection->all();

        expect($all)->toHaveCount(2);
        expect($all['server-1'])->toBe($server);
        expect($all['string-server'])->toBe('string-server');
    });
});

describe('McpServerCollection toTools', function () {
    it('collects tools from resolved servers', function () {
        $tool1 = Tool::make('tool1')->withHandler(fn () => ToolResult::success('1'));
        $tool2 = Tool::make('tool2')->withHandler(fn () => ToolResult::success('2'));
        $tool3 = Tool::make('tool3')->withHandler(fn () => ToolResult::success('3'));

        $serverTools1 = ToolCollection::make([$tool1, $tool2]);
        $serverTools2 = ToolCollection::make([$tool3]);

        $server1 = Mockery::mock(McpServerContract::class);
        $server1->shouldReceive('id')->andReturn('server-1');
        $server1->shouldReceive('tools')->andReturn($serverTools1);

        $server2 = Mockery::mock(McpServerContract::class);
        $server2->shouldReceive('id')->andReturn('server-2');
        $server2->shouldReceive('tools')->andReturn($serverTools2);

        $collection = McpServerCollection::make([$server1, $server2]);

        $tools = $collection->toTools();

        expect($tools)->toBeInstanceOf(ToolCollection::class);
        expect($tools->count())->toBe(3);
        expect($tools->has('tool1'))->toBeTrue();
        expect($tools->has('tool2'))->toBeTrue();
        expect($tools->has('tool3'))->toBeTrue();
    });

    it('returns empty tool collection when no resolved servers', function () {
        $collection = McpServerCollection::make(['string-server-1', 'string-server-2']);

        $tools = $collection->toTools();

        expect($tools)->toBeInstanceOf(ToolCollection::class);
        expect($tools->count())->toBe(0);
    });

    it('ignores unresolved servers when collecting tools', function () {
        $tool = Tool::make('tool1')->withHandler(fn () => ToolResult::success('1'));
        $serverTools = ToolCollection::make([$tool]);

        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');
        $server->shouldReceive('tools')->andReturn($serverTools);

        $collection = McpServerCollection::make([$server, 'string-server']);

        $tools = $collection->toTools();

        expect($tools->count())->toBe(1);
        expect($tools->has('tool1'))->toBeTrue();
    });
});

describe('McpServerCollection connect/disconnect', function () {
    it('connects all resolved servers', function () {
        $server1 = Mockery::mock(McpServerContract::class);
        $server1->shouldReceive('id')->andReturn('server-1');
        $server1->shouldReceive('isConnected')->andReturn(false);
        $server1->shouldReceive('connect')->once();

        $server2 = Mockery::mock(McpServerContract::class);
        $server2->shouldReceive('id')->andReturn('server-2');
        $server2->shouldReceive('isConnected')->andReturn(true);

        $collection = McpServerCollection::make([$server1, $server2, 'string-server']);

        $collection->connectAll();

        // Expectations verified through mock
    });

    it('disconnects all resolved servers', function () {
        $server1 = Mockery::mock(McpServerContract::class);
        $server1->shouldReceive('id')->andReturn('server-1');
        $server1->shouldReceive('isConnected')->andReturn(true);
        $server1->shouldReceive('disconnect')->once();

        $server2 = Mockery::mock(McpServerContract::class);
        $server2->shouldReceive('id')->andReturn('server-2');
        $server2->shouldReceive('isConnected')->andReturn(false);

        $collection = McpServerCollection::make([$server1, $server2, 'string-server']);

        $collection->disconnectAll();

        // Expectations verified through mock
    });
});
