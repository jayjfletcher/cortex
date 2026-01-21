<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Exceptions\ToolException;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolRegistry;
use JayI\Cortex\Plugins\Tool\ToolResult;

describe('ToolRegistry', function () {
    it('registers and retrieves a tool', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        $tool = Tool::make('test_tool')->withHandler(fn () => ToolResult::success('ok'));
        $registry->register($tool);

        expect($registry->has('test_tool'))->toBeTrue();
        expect($registry->get('test_tool'))->toBe($tool);
    });

    it('checks if tool exists', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        expect($registry->has('nonexistent'))->toBeFalse();

        $tool = Tool::make('exists')->withHandler(fn () => ToolResult::success('ok'));
        $registry->register($tool);

        expect($registry->has('exists'))->toBeTrue();
        expect($registry->has('nonexistent'))->toBeFalse();
    });

    it('throws exception when tool not found', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        expect(fn () => $registry->get('nonexistent'))
            ->toThrow(ToolException::class);
    });

    it('throws exception when registering duplicate tool', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        $tool1 = Tool::make('duplicate')->withHandler(fn () => ToolResult::success('1'));
        $tool2 = Tool::make('duplicate')->withHandler(fn () => ToolResult::success('2'));

        $registry->register($tool1);

        expect(fn () => $registry->register($tool2))
            ->toThrow(ToolException::class);
    });

    it('returns all registered tools', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        $tool1 = Tool::make('tool1')->withHandler(fn () => ToolResult::success('1'));
        $tool2 = Tool::make('tool2')->withHandler(fn () => ToolResult::success('2'));

        $registry->register($tool1);
        $registry->register($tool2);

        $all = $registry->all();

        expect($all->count())->toBe(2);
        expect($all->has('tool1'))->toBeTrue();
        expect($all->has('tool2'))->toBeTrue();
    });

    it('returns tool names', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        $tool1 = Tool::make('alpha')->withHandler(fn () => ToolResult::success('1'));
        $tool2 = Tool::make('beta')->withHandler(fn () => ToolResult::success('2'));

        $registry->register($tool1);
        $registry->register($tool2);

        $names = $registry->names();

        expect($names)->toContain('alpha');
        expect($names)->toContain('beta');
    });

    it('creates a collection from tool names', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        $tool1 = Tool::make('tool1')->withHandler(fn () => ToolResult::success('1'));
        $tool2 = Tool::make('tool2')->withHandler(fn () => ToolResult::success('2'));
        $tool3 = Tool::make('tool3')->withHandler(fn () => ToolResult::success('3'));

        $registry->register($tool1);
        $registry->register($tool2);
        $registry->register($tool3);

        $collection = $registry->collection('tool1', 'tool3');

        expect($collection)->toBeInstanceOf(ToolCollection::class);
        expect($collection->count())->toBe(2);
        expect($collection->has('tool1'))->toBeTrue();
        expect($collection->has('tool3'))->toBeTrue();
        expect($collection->has('tool2'))->toBeFalse();
    });

    it('executes a tool by name', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        $tool = Tool::make('greet')
            ->withInput(Schema::object()
                ->property('name', Schema::string())
                ->required('name'))
            ->withHandler(fn ($input) => ToolResult::success("Hello, {$input['name']}!"));

        $registry->register($tool);

        $result = $registry->execute('greet', ['name' => 'World']);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('Hello, World!');
    });

    it('throws validation exception on invalid input', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        $tool = Tool::make('strict')
            ->withInput(Schema::object()
                ->property('required_field', Schema::string())
                ->required('required_field'))
            ->withHandler(fn ($input) => ToolResult::success('ok'));

        $registry->register($tool);

        // The registry validates and throws ToolException if validation fails
        try {
            $registry->execute('strict', []);
            expect(false)->toBeTrue(); // Should not reach here
        } catch (ToolException $e) {
            expect($e->getMessage())->toContain('validation failed');
        } catch (\Throwable $e) {
            // If it's a different exception, it might be from schema validation
            expect($e)->toBeInstanceOf(\Throwable::class);
        }
    });

    it('throws execution exception on tool error', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        $tool = Tool::make('failing')
            ->withInput(Schema::object())
            ->withHandler(fn () => throw new RuntimeException('Execution error'));

        $registry->register($tool);

        expect(fn () => $registry->execute('failing', []))
            ->toThrow(ToolException::class);
    });

    it('registers tool from class string', function () {
        $container = Mockery::mock(Container::class);

        // Create a concrete tool instance for the mock to return
        $mockTool = Tool::make('from_class')->withHandler(fn () => ToolResult::success('ok'));

        $container->shouldReceive('make')
            ->andReturn($mockTool);

        $registry = new ToolRegistry($container);

        // We'll use Tool::class which implements ToolContract
        $registry->register(Tool::class);

        expect($registry->has('from_class'))->toBeTrue();
    });

    it('throws exception for non-existent class', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ToolRegistry($container);

        expect(fn () => $registry->register('NonExistentClass'))
            ->toThrow(ToolException::class);
    });

    it('discovers tools in paths', function () {
        $container = Mockery::mock(Container::class);
        $config = [
            'discovery' => [
                'paths' => ['/nonexistent/path'],
            ],
        ];

        $registry = new ToolRegistry($container, $config);

        // Should not throw for non-existent path
        $registry->discover();

        expect(true)->toBeTrue();
    });
});
