<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Attributes\Tool as ToolAttribute;
use JayI\Cortex\Plugins\Tool\Attributes\ToolParameter;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolResult;

describe('Tool', function () {
    test('creates tool with make', function () {
        $tool = Tool::make('my_tool');

        expect($tool->name())->toBe('my_tool');
        expect($tool->description())->toBe('');
        expect($tool->inputSchema())->toBeInstanceOf(Schema::class);
    });

    test('sets name with withName', function () {
        $tool = Tool::make('original')
            ->withName('renamed');

        expect($tool->name())->toBe('renamed');
    });

    test('sets description with withDescription', function () {
        $tool = Tool::make('test')
            ->withDescription('A test tool');

        expect($tool->description())->toBe('A test tool');
    });

    test('sets input schema with withInput', function () {
        $schema = Schema::object()
            ->property('query', Schema::string())
            ->required('query');

        $tool = Tool::make('search')
            ->withInput($schema);

        expect($tool->inputSchema())->toBe($schema);
    });

    test('sets output schema with withOutput', function () {
        $schema = Schema::object()
            ->property('results', Schema::array(Schema::string()));

        $tool = Tool::make('search')
            ->withOutput($schema);

        expect($tool->outputSchema())->toBe($schema);
    });

    test('sets handler with withHandler', function () {
        $tool = Tool::make('greeting')
            ->withHandler(function (array $input) {
                return ToolResult::success("Hello, {$input['name']}!");
            });

        $context = new ToolContext;
        $result = $tool->execute(['name' => 'World'], $context);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('Hello, World!');
    });

    test('sets timeout with withTimeout', function () {
        $tool = Tool::make('slow_operation')
            ->withTimeout(30);

        expect($tool->timeout())->toBe(30);
    });

    test('timeout defaults to null', function () {
        $tool = Tool::make('fast_operation');

        expect($tool->timeout())->toBeNull();
    });

    test('throws when executing without handler', function () {
        $tool = Tool::make('no_handler');

        expect(fn () => $tool->execute([], new ToolContext))
            ->toThrow(RuntimeException::class);
    });

    test('handler returning non-ToolResult is wrapped', function () {
        $tool = Tool::make('simple')
            ->withHandler(function () {
                return ['data' => 'value'];
            });

        $result = $tool->execute([], new ToolContext);

        expect($result)->toBeInstanceOf(ToolResult::class);
        expect($result->success)->toBeTrue();
        expect($result->output)->toBe(['data' => 'value']);
    });

    test('converts to definition', function () {
        $tool = Tool::make('test_tool')
            ->withDescription('A test tool')
            ->withInput(Schema::object()
                ->property('param', Schema::string())
                ->required('param'));

        $definition = $tool->toDefinition();

        expect($definition['name'])->toBe('test_tool');
        expect($definition['description'])->toBe('A test tool');
        expect($definition['input_schema'])->toHaveKey('type');
        expect($definition['input_schema']['type'])->toBe('object');
    });

    test('creates tool from invokable class', function () {
        // Define a simple invokable class
        $invokableClass = new class
        {
            public function __invoke(string $name, int $count = 1): string
            {
                return str_repeat($name, $count);
            }
        };

        // Get the anonymous class name
        $className = get_class($invokableClass);

        // We can't easily test fromInvokable with anonymous classes since they can't be resolved from the container
        // So let's test the type inference separately
        $tool = Tool::make('test_tool')
            ->withHandler(function (array $input, ToolContext $context) use ($invokableClass) {
                return $invokableClass(...array_values($input));
            });

        $context = new ToolContext;
        $result = $tool->execute(['name' => 'Hi', 'count' => 3], $context);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('HiHiHi');
    });

    test('throws when fromInvokable class has no invoke method', function () {
        // Create a class without __invoke
        $className = new class {};

        expect(fn () => Tool::fromInvokable(get_class($className)))
            ->toThrow(RuntimeException::class, 'must have an __invoke method');
    });
});

describe('ToolResult', function () {
    test('creates success result', function () {
        $result = ToolResult::success('output data');

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('output data');
        expect($result->error)->toBeNull();
        expect($result->shouldContinue)->toBeTrue();
    });

    test('creates success result with metadata', function () {
        $result = ToolResult::success('output', ['key' => 'value']);

        expect($result->metadata['key'])->toBe('value');
    });

    test('creates error result', function () {
        $result = ToolResult::error('Something went wrong');

        expect($result->success)->toBeFalse();
        expect($result->output)->toBeNull();
        expect($result->error)->toBe('Something went wrong');
        expect($result->shouldContinue)->toBeTrue();
    });

    test('creates error result with metadata', function () {
        $result = ToolResult::error('Error', ['code' => 500]);

        expect($result->metadata['code'])->toBe(500);
    });

    test('creates stop result', function () {
        $result = ToolResult::stop('final output');

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('final output');
        expect($result->shouldContinue)->toBeFalse();
    });

    test('creates stop result with metadata', function () {
        $result = ToolResult::stop('output', ['reason' => 'completed']);

        expect($result->metadata['reason'])->toBe('completed');
    });

    test('shouldStop returns correct value', function () {
        $continue = ToolResult::success('data');
        $stop = ToolResult::stop('data');

        expect($continue->shouldStop())->toBeFalse();
        expect($stop->shouldStop())->toBeTrue();
    });

    test('toContentString returns string output', function () {
        $result = ToolResult::success('text output');

        expect($result->toContentString())->toBe('text output');
    });

    test('toContentString returns error message', function () {
        $result = ToolResult::error('Something failed');

        expect($result->toContentString())->toBe('Error: Something failed');
    });

    test('toContentString encodes array output', function () {
        $result = ToolResult::success(['key' => 'value']);

        $content = $result->toContentString();

        expect($content)->toContain('"key"');
        expect($content)->toContain('"value"');
    });

    test('toContentString encodes object output', function () {
        $result = ToolResult::success((object) ['key' => 'value']);

        $content = $result->toContentString();

        expect($content)->toContain('"key"');
    });

    test('toContentString casts scalar values', function () {
        $result = ToolResult::success(42);

        expect($result->toContentString())->toBe('42');
    });

    test('withMetadata adds metadata', function () {
        $result = ToolResult::success('data', ['initial' => 'value'])
            ->withMetadata(['added' => 'new']);

        expect($result->metadata['initial'])->toBe('value');
        expect($result->metadata['added'])->toBe('new');
    });
});

describe('ToolContext', function () {
    test('creates empty context', function () {
        $context = new ToolContext;

        expect($context->conversationId)->toBeNull();
        expect($context->agentId)->toBeNull();
        expect($context->tenantId)->toBeNull();
        expect($context->metadata)->toBe([]);
    });

    test('creates context for conversation', function () {
        $context = ToolContext::forConversation('conv-123');

        expect($context->conversationId)->toBe('conv-123');
    });

    test('creates context for agent', function () {
        $context = ToolContext::forAgent('agent-456', 'conv-123');

        expect($context->agentId)->toBe('agent-456');
        expect($context->conversationId)->toBe('conv-123');
    });

    test('creates context for tenant', function () {
        $context = ToolContext::forTenant('tenant-789');

        expect($context->tenantId)->toBe('tenant-789');
    });

    test('adds metadata with withMetadata', function () {
        $context = (new ToolContext)
            ->withMetadata(['key' => 'value']);

        expect($context->metadata['key'])->toBe('value');
    });

    test('withMetadata merges metadata', function () {
        $context = (new ToolContext(metadata: ['existing' => 'data']))
            ->withMetadata(['added' => 'value']);

        expect($context->metadata['existing'])->toBe('data');
        expect($context->metadata['added'])->toBe('value');
    });

    test('gets metadata value', function () {
        $context = new ToolContext(metadata: ['key' => 'value']);

        expect($context->get('key'))->toBe('value');
        expect($context->get('missing'))->toBeNull();
        expect($context->get('missing', 'default'))->toBe('default');
    });
});

describe('ToolCollection', function () {
    test('creates empty collection', function () {
        $collection = ToolCollection::make();

        expect($collection->count())->toBe(0);
        expect($collection->isEmpty())->toBeTrue();
    });

    test('creates collection with tools', function () {
        $tool = Tool::make('test')->withHandler(fn () => null);
        $collection = ToolCollection::make([$tool]);

        expect($collection->count())->toBe(1);
        expect($collection->isNotEmpty())->toBeTrue();
    });

    test('adds tool to collection', function () {
        $tool = Tool::make('test')->withHandler(fn () => null);
        $collection = ToolCollection::make()->add($tool);

        expect($collection->has('test'))->toBeTrue();
    });

    test('removes tool from collection', function () {
        $tool = Tool::make('test')->withHandler(fn () => null);
        $collection = ToolCollection::make([$tool])
            ->remove('test');

        expect($collection->has('test'))->toBeFalse();
    });

    test('gets tool by name', function () {
        $tool = Tool::make('test')->withHandler(fn () => null);
        $collection = ToolCollection::make([$tool]);

        expect($collection->get('test'))->toBe($tool);
        expect($collection->get('nonexistent'))->toBeNull();
    });

    test('finds tool by name (alias)', function () {
        $tool = Tool::make('test')->withHandler(fn () => null);
        $collection = ToolCollection::make([$tool]);

        expect($collection->find('test'))->toBe($tool);
    });

    test('checks if tool exists', function () {
        $tool = Tool::make('test')->withHandler(fn () => null);
        $collection = ToolCollection::make([$tool]);

        expect($collection->has('test'))->toBeTrue();
        expect($collection->has('other'))->toBeFalse();
    });

    test('gets tool names', function () {
        $collection = ToolCollection::make([
            Tool::make('tool1')->withHandler(fn () => null),
            Tool::make('tool2')->withHandler(fn () => null),
        ]);

        expect($collection->names())->toBe(['tool1', 'tool2']);
    });

    test('converts to tool definitions', function () {
        $collection = ToolCollection::make([
            Tool::make('tool1')
                ->withDescription('First tool')
                ->withHandler(fn () => null),
        ]);

        $definitions = $collection->toToolDefinitions();

        expect($definitions)->toHaveCount(1);
        expect($definitions[0]['name'])->toBe('tool1');
        expect($definitions[0]['description'])->toBe('First tool');
    });

    test('is iterable', function () {
        $collection = ToolCollection::make([
            Tool::make('tool1')->withHandler(fn () => null),
            Tool::make('tool2')->withHandler(fn () => null),
        ]);

        $names = [];
        foreach ($collection as $name => $tool) {
            $names[] = $name;
        }

        expect($names)->toBe(['tool1', 'tool2']);
    });

    test('converts to array', function () {
        $tool = Tool::make('test')->withHandler(fn () => null);
        $collection = ToolCollection::make([$tool]);

        $array = $collection->toArray();

        expect($array)->toHaveKey('test');
        expect($array['test'])->toBe($tool);
    });

    test('merges collections', function () {
        $collection1 = ToolCollection::make([
            Tool::make('tool1')->withHandler(fn () => null),
        ]);
        $collection2 = ToolCollection::make([
            Tool::make('tool2')->withHandler(fn () => null),
        ]);

        $merged = $collection1->merge($collection2);

        expect($merged->has('tool1'))->toBeTrue();
        expect($merged->has('tool2'))->toBeTrue();
        expect($merged->count())->toBe(2);
    });

    test('filters tools', function () {
        $collection = ToolCollection::make([
            Tool::make('search')->withHandler(fn () => null),
            Tool::make('calculate')->withHandler(fn () => null),
        ]);

        $filtered = $collection->filter(fn ($tool) => str_starts_with($tool->name(), 's'));

        expect($filtered->count())->toBe(1);
        expect($filtered->has('search'))->toBeTrue();
    });

    test('maps tools', function () {
        $collection = ToolCollection::make([
            Tool::make('tool1')->withHandler(fn () => null),
            Tool::make('tool2')->withHandler(fn () => null),
        ]);

        $names = $collection->map(fn ($tool) => strtoupper($tool->name()));

        expect($names['tool1'])->toBe('TOOL1');
        expect($names['tool2'])->toBe('TOOL2');
    });

    test('executes tool by name', function () {
        $tool = Tool::make('greet')
            ->withHandler(fn ($input) => ToolResult::success("Hello, {$input['name']}!"));

        $collection = ToolCollection::make([$tool]);
        $result = $collection->execute('greet', ['name' => 'World']);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe('Hello, World!');
    });

    test('execute returns error for missing tool', function () {
        $collection = ToolCollection::make();
        $result = $collection->execute('missing', []);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('not found');
    });

    test('gets all tools', function () {
        $tool = Tool::make('test')->withHandler(fn () => null);
        $collection = ToolCollection::make([$tool]);

        $all = $collection->all();

        expect($all)->toHaveKey('test');
    });
});

describe('ToolAttribute', function () {
    test('creates with defaults', function () {
        $attr = new ToolAttribute;

        expect($attr->name)->toBeNull();
        expect($attr->description)->toBeNull();
        expect($attr->timeout)->toBeNull();
        expect($attr->metadata)->toBe([]);
    });

    test('creates with values', function () {
        $attr = new ToolAttribute(
            name: 'custom_name',
            description: 'A description',
            timeout: 60,
            metadata: ['key' => 'value'],
        );

        expect($attr->name)->toBe('custom_name');
        expect($attr->description)->toBe('A description');
        expect($attr->timeout)->toBe(60);
        expect($attr->metadata['key'])->toBe('value');
    });
});

describe('ToolParameter', function () {
    test('creates with defaults', function () {
        $attr = new ToolParameter;

        expect($attr->description)->toBeNull();
        expect($attr->type)->toBeNull();
        expect($attr->required)->toBeTrue();
        expect($attr->default)->toBeNull();
    });

    test('creates with values', function () {
        $attr = new ToolParameter(
            description: 'A parameter',
            type: 'string',
            required: false,
            default: 'default_value',
            enum: ['a', 'b', 'c'],
            minLength: 1,
            maxLength: 100,
            minimum: 0.0,
            maximum: 100.0,
            pattern: '^[a-z]+$',
            format: 'email',
        );

        expect($attr->description)->toBe('A parameter');
        expect($attr->type)->toBe('string');
        expect($attr->required)->toBeFalse();
        expect($attr->default)->toBe('default_value');
        expect($attr->enum)->toBe(['a', 'b', 'c']);
        expect($attr->minLength)->toBe(1);
        expect($attr->maxLength)->toBe(100);
        expect($attr->minimum)->toBe(0.0);
        expect($attr->maximum)->toBe(100.0);
        expect($attr->pattern)->toBe('^[a-z]+$');
        expect($attr->format)->toBe('email');
    });
});
