<?php

declare(strict_types=1);

use JayI\Cortex\Exceptions\McpException;
use JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract;
use JayI\Cortex\Plugins\Mcp\McpRegistry;
use JayI\Cortex\Plugins\Mcp\McpTransport;

describe('McpRegistry', function () {
    beforeEach(function () {
        $this->registry = new McpRegistry;
    });

    test('registers and retrieves server', function () {
        $mockServer = Mockery::mock(McpServerContract::class);
        $mockServer->shouldReceive('id')->andReturn('test-server');

        $this->registry->register($mockServer);

        expect($this->registry->has('test-server'))->toBeTrue();
        expect($this->registry->get('test-server'))->toBe($mockServer);
    });

    test('returns false for non-existent server', function () {
        expect($this->registry->has('unknown'))->toBeFalse();
    });

    test('throws exception for non-existent server', function () {
        expect(fn() => $this->registry->get('unknown'))
            ->toThrow(McpException::class);
    });

    test('returns all servers as collection', function () {
        $server1 = Mockery::mock(McpServerContract::class);
        $server1->shouldReceive('id')->andReturn('server-1');

        $server2 = Mockery::mock(McpServerContract::class);
        $server2->shouldReceive('id')->andReturn('server-2');

        $this->registry->register($server1);
        $this->registry->register($server2);

        $all = $this->registry->all();

        expect($all)->toHaveCount(2);
        expect($all->has('server-1'))->toBeTrue();
        expect($all->has('server-2'))->toBeTrue();
    });

    test('connects all servers', function () {
        $server1 = Mockery::mock(McpServerContract::class);
        $server1->shouldReceive('id')->andReturn('server-1');
        $server1->shouldReceive('isConnected')->andReturn(false);
        $server1->shouldReceive('connect')->once();

        $server2 = Mockery::mock(McpServerContract::class);
        $server2->shouldReceive('id')->andReturn('server-2');
        $server2->shouldReceive('isConnected')->andReturn(true);

        $this->registry->register($server1);
        $this->registry->register($server2);

        $this->registry->connectAll();
    });

    test('disconnects all servers', function () {
        $server1 = Mockery::mock(McpServerContract::class);
        $server1->shouldReceive('id')->andReturn('server-1');
        $server1->shouldReceive('isConnected')->andReturn(true);
        $server1->shouldReceive('disconnect')->once();

        $server2 = Mockery::mock(McpServerContract::class);
        $server2->shouldReceive('id')->andReturn('server-2');
        $server2->shouldReceive('isConnected')->andReturn(false);

        $this->registry->register($server1);
        $this->registry->register($server2);

        $this->registry->disconnectAll();
    });
});
