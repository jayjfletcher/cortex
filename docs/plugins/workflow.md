# Workflow Plugin

The Workflow plugin provides a powerful system for orchestrating complex, multi-step processes. It enables you to build workflows composed of nodes connected by edges, with support for branching, looping, parallel execution, and human-in-the-loop patterns.

## Installation

The Workflow plugin is included with Cortex and requires the Schema, Provider, Chat, Tool, and Agent plugins:

```php
use JayI\Cortex\Plugins\Workflow\WorkflowPlugin;

$pluginManager->register(new WorkflowPlugin($container, [
    'max_steps' => 1000, // Optional: maximum execution steps
]));
```

## Quick Start

Create and run a simple workflow:

```php
use JayI\Cortex\Plugins\Workflow\Workflow;
use JayI\Cortex\Plugins\Workflow\NodeResult;

$workflow = Workflow::make('hello-workflow')
    ->withName('Hello World')
    ->withDescription('A simple greeting workflow')
    ->callback('greet', fn ($input, $state) => NodeResult::success([
        'greeting' => "Hello, {$input['name']}!",
    ]));

$result = $workflow->run(['name' => 'World']);

echo $result->get('greeting'); // "Hello, World!"
```

## Core Concepts

### Workflow

A workflow is a directed graph of nodes connected by edges:

```php
$workflow = Workflow::make('process-order')
    ->withName('Order Processing')
    ->withDescription('Process customer orders')
    ->callback('validate', fn ($input, $state) => NodeResult::success([
        'valid' => true,
        'order_id' => $input['order_id'],
    ]))
    ->callback('charge', fn ($input, $state) => NodeResult::success([
        'charged' => true,
        'amount' => $input['amount'],
    ]))
    ->callback('ship', fn ($input, $state) => NodeResult::success([
        'shipped' => true,
        'tracking' => 'TRACK123',
    ]))
    ->then('validate', 'charge')
    ->then('charge', 'ship');
```

### Nodes

Nodes are the building blocks of workflows. Each node executes a specific task and returns a `NodeResult`.

#### CallbackNode

Executes a custom callback function:

```php
$workflow->callback('process', function (array $input, WorkflowState $state) {
    // Your processing logic
    $result = doSomething($input['data']);

    return NodeResult::success(['processed' => $result]);
});
```

#### ConditionNode

Branches based on a condition:

```php
$workflow->condition(
    'check-amount',
    fn ($input, $state) => $input['amount'] > 100,
    ['true' => 'premium-processing', 'false' => 'standard-processing']
);
```

#### LoopNode

Repeats a node while a condition is true:

```php
use JayI\Cortex\Plugins\Workflow\Nodes\CallbackNode;
use JayI\Cortex\Plugins\Workflow\Nodes\LoopNode;

$body = new CallbackNode('increment', fn ($input, $state) =>
    NodeResult::success(['counter' => ($input['counter'] ?? 0) + 1])
);

$workflow->addNode(new LoopNode(
    'count-loop',
    $body,
    fn ($input, $state, $iteration) => ($input['counter'] ?? 0) < 5,
    maxIterations: 100
));
```

#### ParallelNode

Executes multiple nodes concurrently:

```php
use JayI\Cortex\Plugins\Workflow\Nodes\ParallelNode;

$nodes = [
    new CallbackNode('task-a', fn () => NodeResult::success(['a' => 'done'])),
    new CallbackNode('task-b', fn () => NodeResult::success(['b' => 'done'])),
    new CallbackNode('task-c', fn () => NodeResult::success(['c' => 'done'])),
];

$workflow->addNode(new ParallelNode(
    'parallel-tasks',
    $nodes,
    mergeStrategy: 'all' // 'all', 'any', or 'custom'
));
```

#### HumanInputNode

Pauses for human input:

```php
use JayI\Cortex\Plugins\Schema\Schema;

$workflow->humanInput(
    'get-approval',
    'Please approve this request',
    Schema::object([
        'approved' => Schema::boolean(),
        'comments' => Schema::string(),
    ]),
    timeout: 3600 // Optional timeout in seconds
);
```

#### AgentNode

Executes an AI agent:

```php
$workflow->agent(
    'analyze',
    'data-analyzer', // Agent ID from registry
    inputMapping: [
        'query' => '$input.question',
        'context' => '$state.context',
    ],
    outputKey: 'analysis'
);
```

#### ToolNode

Executes a tool:

```php
$workflow->tool(
    'search',
    'web-search', // Tool ID from registry
    inputMapping: fn ($input, $state) => [
        'query' => $input['search_term'],
    ],
    outputKey: 'search_results'
);
```

#### SubWorkflowNode

Executes another workflow:

```php
$workflow->subWorkflow(
    'process-items',
    'item-processor', // Workflow ID from registry
    inputMapping: ['items' => '$state.items'],
    outputKey: 'processed_items'
);
```

### Edges

Edges connect nodes and can have conditions:

```php
// Simple edge
$workflow->then('step1', 'step2');

// Edge with condition
$workflow->edge('validate', 'premium', fn ($input) => $input['tier'] === 'premium');
$workflow->edge('validate', 'standard', fn ($input) => $input['tier'] !== 'premium');

// Edge with priority (higher executes first when multiple match)
$workflow->edge('start', 'high-priority', priority: 10);
$workflow->edge('start', 'low-priority', priority: 0);
```

### NodeResult

Nodes return `NodeResult` to indicate their outcome:

```php
// Success with output
NodeResult::success(['key' => 'value']);

// Failure with error message
NodeResult::failure('Something went wrong');

// Pause for input
NodeResult::pause('Waiting for approval', ['context' => 'data']);

// Jump to specific node
NodeResult::goto('specific-node', ['output' => 'value']);
```

## Workflow State

The workflow maintains state throughout execution:

```php
// Create initial state
$state = WorkflowState::start('workflow-id', 'run-123', 'entry-node');

// Access data
$value = $state->get('key', 'default');

// Check if key exists
if ($state->has('key')) {
    // ...
}

// Modify state (immutable)
$newState = $state->set('key', 'value');
$newState = $state->merge(['a' => 1, 'b' => 2]);
```

## Workflow Execution

### Running Workflows

```php
use JayI\Cortex\Plugins\Workflow\WorkflowContext;

// Simple run
$result = $workflow->run(['input' => 'data']);

// With context
$context = new WorkflowContext(
    correlationId: 'corr-123',
    tenantId: 'tenant-456',
    metadata: ['source' => 'api'],
);

$result = $workflow->run(['input' => 'data'], $context);
```

### Handling Results

```php
if ($result->isCompleted()) {
    $output = $result->output();
    $value = $result->get('key');
}

if ($result->isFailed()) {
    $error = $result->error;
    $state = $result->state;
}

if ($result->isPaused()) {
    $reason = $result->pauseReason;
    $state = $result->state; // Save for resuming
}
```

### Resuming Paused Workflows

```php
// Workflow paused for human input
$result = $workflow->run(['order_id' => '123']);

if ($result->isPaused()) {
    // Save state for later
    $savedState = $result->state;

    // Later, resume with input
    $resumedResult = $workflow->resume($savedState, [
        'human_input' => ['approved' => true],
    ]);
}
```

## Input Mapping

Map inputs dynamically using closures or static mappings:

```php
// Static mapping with references
$workflow->tool('search', 'web-search', [
    'query' => '$input.search_term',    // From workflow input
    'limit' => '$state.max_results',     // From workflow state
    'format' => 'json',                  // Static value
]);

// Dynamic mapping with closure
$workflow->agent('analyze', 'analyzer', fn ($input, $state) => [
    'data' => array_merge($input['data'], $state->get('additional_data', [])),
    'options' => [
        'format' => 'detailed',
        'include_metadata' => true,
    ],
]);
```

## Workflow Registry

Register and retrieve workflows:

```php
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowRegistryContract;
use JayI\Cortex\Plugins\Workflow\WorkflowCollection;

$registry = app(WorkflowRegistryContract::class);

// Register a workflow
$registry->register($workflow);

// Retrieve by ID
$workflow = $registry->get('my-workflow');

// Check existence
if ($registry->has('my-workflow')) {
    // ...
}

// Get all workflows (returns WorkflowCollection)
$all = $registry->all();

// Get specific workflows (returns WorkflowCollection)
$subset = $registry->only(['process-order', 'approval-workflow']);

// Get all except specified (returns WorkflowCollection)
$filtered = $registry->except(['deprecated-workflow']);
```

## Custom Executor

Configure the workflow executor:

```php
use JayI\Cortex\Plugins\Workflow\WorkflowExecutor;

$executor = (new WorkflowExecutor())
    ->maxSteps(500);

$workflow = Workflow::make('my-workflow')
    ->executor($executor)
    ->callback('step', fn () => NodeResult::success([]));
```

## Workflow History

Access execution history:

```php
$result = $workflow->run($input);

foreach ($result->state->history as $entry) {
    echo "Node: {$entry->nodeId}\n";
    echo "Duration: {$entry->duration}s\n";
    echo "Success: " . ($entry->success ? 'Yes' : 'No') . "\n";

    if ($entry->error) {
        echo "Error: {$entry->error}\n";
    }
}
```

## Complete Example

A complete order processing workflow:

```php
use JayI\Cortex\Plugins\Workflow\Workflow;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Schema\Schema;

$orderWorkflow = Workflow::make('process-order')
    ->withName('Order Processing')
    ->withDescription('Complete order processing workflow')

    // Validate order
    ->callback('validate', function ($input, $state) {
        if (empty($input['order_id'])) {
            return NodeResult::failure('Order ID required');
        }

        return NodeResult::success([
            'order_id' => $input['order_id'],
            'valid' => true,
        ]);
    })

    // Check inventory
    ->callback('check-inventory', function ($input, $state) {
        // Simulate inventory check
        $available = true;

        return NodeResult::success([
            'in_stock' => $available,
        ]);
    })

    // Condition: in stock or not
    ->condition(
        'stock-check',
        fn ($input, $state) => $state->get('in_stock'),
        ['true' => 'process-payment', 'false' => 'notify-backorder']
    )

    // Process payment
    ->callback('process-payment', function ($input, $state) {
        return NodeResult::success([
            'payment_status' => 'completed',
            'transaction_id' => 'TXN-' . uniqid(),
        ]);
    })

    // Handle backorder
    ->callback('notify-backorder', function ($input, $state) {
        return NodeResult::success([
            'notification_sent' => true,
            'status' => 'backordered',
        ]);
    })

    // Request approval for large orders
    ->humanInput(
        'manager-approval',
        'Please approve this large order',
        Schema::object([
            'approved' => Schema::boolean(),
            'notes' => Schema::string(),
        ])
    )

    // Ship order
    ->callback('ship', function ($input, $state) {
        return NodeResult::success([
            'shipped' => true,
            'tracking' => 'SHIP-' . uniqid(),
        ]);
    })

    // Complete
    ->callback('complete', function ($input, $state) {
        return NodeResult::success([
            'status' => 'completed',
            'completed_at' => date('c'),
        ]);
    })

    // Define edges
    ->then('validate', 'check-inventory')
    ->then('check-inventory', 'stock-check')
    ->then('process-payment', 'manager-approval')
    ->then('notify-backorder', 'complete')
    ->edge('manager-approval', 'ship', fn ($input) => $input['human_input']['approved'] ?? false)
    ->edge('manager-approval', 'complete', fn ($input) => !($input['human_input']['approved'] ?? false))
    ->then('ship', 'complete');

// Run the workflow
$result = $orderWorkflow->run(['order_id' => 'ORD-123']);

if ($result->isPaused()) {
    // Handle human approval
    $result = $orderWorkflow->resume($result->state, [
        'human_input' => ['approved' => true, 'notes' => 'Looks good'],
    ]);
}

echo "Order status: " . $result->get('status');
```

## Error Handling

### Plugin Dependency Exceptions

The `Workflow` class requires specific plugins to be enabled for certain node types. A `PluginException` is thrown if the required plugin is not registered:

```php
use JayI\Cortex\Plugins\Workflow\Workflow;
use JayI\Cortex\Exceptions\PluginException;

// Requires 'agent' plugin to be enabled
try {
    $workflow = Workflow::make('my-workflow')
        ->agent('analyze', 'data-analyzer');  // Throws if agent plugin disabled
} catch (PluginException $e) {
    // "Plugin [agent] is disabled."
}

// Requires 'tool' plugin to be enabled
try {
    $workflow = Workflow::make('my-workflow')
        ->tool('search', 'web-search');  // Throws if tool plugin disabled
} catch (PluginException $e) {
    // "Plugin [tool] is disabled."
}
```

**Methods requiring `agent` plugin:**
- `agent()` - Add an agent node

**Methods requiring `tool` plugin:**
- `tool()` - Add a tool node

## API Reference

### Workflow

| Method | Description |
|--------|-------------|
| `make(string $id)` | Create a new workflow |
| `withName(string $name)` | Set workflow name |
| `withDescription(string $description)` | Set description |
| `metadata(array $metadata)` | Add metadata |
| `callback(string $nodeId, Closure $callback)` | Add callback node |
| `condition(string $nodeId, Closure $condition, array $branches)` | Add condition node |
| `loop(string $nodeId, NodeContract $body, Closure $condition, int $maxIterations)` | Add loop node |
| `parallel(string $nodeId, array $nodes, string $mergeStrategy, ?Closure $merger)` | Add parallel node |
| `humanInput(string $nodeId, string $prompt, ?Schema $schema, ?int $timeout)` | Add human input node |
| `agent(string $nodeId, AgentContract\|string $agent, array\|Closure $inputMapping, ?string $outputKey)` | Add agent node |
| `tool(string $nodeId, ToolContract\|string $tool, array\|Closure $inputMapping, ?string $outputKey)` | Add tool node |
| `subWorkflow(string $nodeId, WorkflowContract\|string $workflow, array\|Closure $inputMapping, ?string $outputKey)` | Add sub-workflow node |
| `then(string $from, string $to)` | Add simple edge |
| `edge(string $from, string $to, ?Closure $condition, int $priority)` | Add conditional edge |
| `entry(string $nodeId)` | Set entry node |
| `run(array $input, ?WorkflowContext $context)` | Execute workflow |
| `resume(WorkflowState $state, array $input)` | Resume paused workflow |

### NodeResult

| Method | Description |
|--------|-------------|
| `success(array $output, ?string $nextNode)` | Create success result |
| `failure(string $error)` | Create failure result |
| `pause(string $reason, array $output)` | Create pause result |
| `goto(string $nodeId, array $output)` | Jump to specific node |

### WorkflowResult

| Property | Description |
|----------|-------------|
| `state` | Final workflow state |
| `completed` | Whether workflow completed |
| `paused` | Whether workflow is paused |
| `pauseReason` | Reason for pause |
| `error` | Error message if failed |

| Method | Description |
|--------|-------------|
| `isCompleted()` | Check if completed successfully |
| `isFailed()` | Check if failed |
| `isPaused()` | Check if paused |
| `output()` | Get all output data |
| `get(string $key, mixed $default)` | Get specific output value |

## Workflow Persistence

Persist workflow state for long-running and resumable workflows.

### Persistent Workflow Executor

```php
use JayI\Cortex\Plugins\Workflow\PersistentWorkflowExecutor;

$executor = app(PersistentWorkflowExecutor::class);

// Execute with persistence
$result = $executor->execute($workflow, ['input' => 'data']);

// Get run ID for later
$runId = $result->state->runId;
```

### Resuming Workflows

```php
// Find and resume a paused workflow
$state = $executor->find($runId);

if ($state->status === WorkflowStatus::Paused) {
    $result = $executor->resume($workflow, $state, [
        'approval' => true,
    ]);
}
```

### Repository Implementations

#### Cache Repository

```php
use JayI\Cortex\Plugins\Workflow\Repositories\CacheWorkflowStateRepository;

// Uses Laravel cache
$repository = new CacheWorkflowStateRepository(
    cache: app('cache.store'),
    prefix: 'workflow',
    ttl: 86400,  // 24 hours
);
```

#### Database Repository

```php
use JayI\Cortex\Plugins\Workflow\Repositories\DatabaseWorkflowStateRepository;

// Uses database table
$repository = new DatabaseWorkflowStateRepository();
```

### Finding Paused Workflows

```php
// Find all paused workflows for a specific workflow definition
$pausedStates = $repository->findPaused('approval-workflow');

foreach ($pausedStates as $state) {
    echo "Run {$state->runId} paused at: {$state->currentNode}\n";
    echo "Reason: {$state->pauseReason}\n";
}
```

### WorkflowState

The `WorkflowState` class tracks execution state:

```php
use JayI\Cortex\Plugins\Workflow\WorkflowState;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

// Create initial state
$state = WorkflowState::start('workflow-id', 'run-123', 'entry-node');

// State transitions (immutable)
$state = $state->moveTo('next-node');
$state = $state->merge(['key' => 'value']);
$state = $state->pause('Waiting for approval');
$state = $state->resume();
$state = $state->complete();
$state = $state->fail();

// Record node execution
$state = $state->recordNodeExecution(
    nodeId: 'process-node',
    input: ['key' => 'input'],
    output: ['key' => 'output'],
    duration: 0.5,
);
```

### WorkflowStatus Enum

```php
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

WorkflowStatus::Pending;    // Not started
WorkflowStatus::Running;    // Currently executing
WorkflowStatus::Paused;     // Waiting for input
WorkflowStatus::Completed;  // Finished successfully
WorkflowStatus::Failed;     // Encountered error

// Check if can resume
$status->canResume();  // true only for Paused

// Check if terminal state
$status->isTerminal(); // true for Completed/Failed
```

### Serialization

Workflow state can be serialized for storage:

```php
// To array
$array = $state->toArray();

// From array
$state = WorkflowState::fromArray($array);
```

### Database Migration

Run the migration to create the workflow states table:

```bash
php artisan migrate
```

The migration creates a `cortex_workflow_states` table with columns for:
- `run_id` (primary key)
- `workflow_id`
- `current_node`
- `status`
- `data` (JSON)
- `history` (JSON)
- `pause_reason`
- `started_at`, `updated_at`, `paused_at`, `completed_at`

### Persistence Configuration

```php
// config/cortex.php
'workflow' => [
    'persistence' => [
        'driver' => 'database',  // or 'cache'
        'table' => 'cortex_workflow_states',
        'ttl' => 86400,  // For cache driver
    ],
],
```

### Persistence Complete Example

```php
use JayI\Cortex\Plugins\Workflow\Workflow;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\PersistentWorkflowExecutor;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

// Create workflow with human input
$workflow = Workflow::make('approval-workflow')
    ->callback('prepare', fn ($input) => NodeResult::success([
        'document' => $input['document'],
        'prepared_at' => now()->toIso8601String(),
    ]))
    ->humanInput('get-approval', 'Please review and approve this document')
    ->callback('process', fn ($input) => NodeResult::success([
        'processed' => true,
        'approved_by' => $input['human_input']['approver'] ?? 'unknown',
    ]))
    ->then('prepare', 'get-approval')
    ->then('get-approval', 'process');

$executor = app(PersistentWorkflowExecutor::class);

// Start workflow - will pause at human input
$result = $executor->execute($workflow, ['document' => 'Report.pdf']);
$runId = $result->state->runId;

// Later, find and resume
$state = $executor->find($runId);

if ($state && $state->status === WorkflowStatus::Paused) {
    $result = $executor->resume($workflow, $state, [
        'human_input' => [
            'approved' => true,
            'approver' => 'John Doe',
            'comments' => 'Looks good!',
        ],
    ]);

    if ($result->isCompleted()) {
        echo "Workflow completed!\n";
        echo "Approved by: " . $result->get('approved_by') . "\n";
    }
}
```
