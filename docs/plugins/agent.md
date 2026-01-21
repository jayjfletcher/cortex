# Agent Plugin

The Agent plugin provides autonomous agents with tool use, memory management, and agentic loops for complex multi-step tasks.

## Overview

- **Plugin ID:** `agent`
- **Dependencies:** `schema`, `provider`, `chat`, `tool`
- **Provides:** `agents`

## Creating Agents

### Fluent Builder

```php
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\Memory\BufferMemory;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolResult;
use JayI\Cortex\Plugins\Schema\Schema;

$agent = Agent::make('research-assistant')
    ->withName('Research Assistant')
    ->withDescription('An agent that helps with research tasks')
    ->withSystemPrompt('You are a helpful research assistant. Use the available tools to find information and answer questions.')
    ->withModel('anthropic.claude-3-5-sonnet-20241022-v2:0')
    ->withMaxIterations(10)
    ->withMemory(new BufferMemory())
    ->withTools([
        Tool::make('search')
            ->withDescription('Search for information')
            ->withInput(Schema::object()->property('query', Schema::string())->required('query'))
            ->withHandler(fn ($input) => ToolResult::success(SearchService::search($input['query']))),
    ]);
```

### Running Agents

```php
use JayI\Cortex\Plugins\Agent\AgentContext;

// Simple run
$response = $agent->run('What is the capital of France?');

echo $response->content; // The agent's final response

// With context
$context = new AgentContext(
    conversationId: 'conv-123',
    tenantId: 'tenant-456',
);

$response = $agent->run('Continue our previous discussion', $context);
```

## Agent Contract

All agents implement `AgentContract`:

```php
interface AgentContract
{
    public function id(): string;
    public function name(): string;
    public function description(): string;
    public function systemPrompt(): string;
    public function tools(): ToolCollection;
    public function model(): ?string;
    public function provider(): ?string;
    public function maxIterations(): int;
    public function memory(): ?MemoryContract;
    public function run(string|array $input, ?AgentContext $context = null): AgentResponse;
    public function stream(string|array $input, ?AgentContext $context = null): AgentResponse;
}
```

## Agent Context

Provide execution context to agents:

```php
use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;

$context = new AgentContext(
    conversationId: 'conv-123',    // Track conversation
    runId: 'run-456',               // Track this specific run
    tenantId: 'tenant-789',         // Multi-tenancy support
    history: $previousMessages,     // Pre-existing message history
    metadata: ['custom' => 'data'], // Custom metadata
);

// Immutable updates
$context = $context
    ->withConversationId('new-conv')
    ->withMetadata(['key' => 'value']);
```

## Agent Response

The `AgentResponse` contains the complete result of an agent run:

```php
$response = $agent->run('Hello');

// Content
echo $response->content;           // Final text response

// Completion status
$response->isComplete();           // true if completed naturally
$response->hitMaxIterations();     // true if hit iteration limit
$response->stopReason;             // AgentStopReason enum

// Iterations
$response->iterationCount;         // Number of iterations
$response->iterations;             // AgentIteration[]
$response->lastIteration();        // Get last iteration

// Tool usage
$toolCalls = $response->toolCalls(); // All tool calls made
// [['tool' => 'search', 'input' => [...], 'output' => ...], ...]

// Token usage
$response->totalUsage->inputTokens;
$response->totalUsage->outputTokens;

// Messages
$response->messages;               // Full conversation history
```

### Stop Reasons

```php
use JayI\Cortex\Plugins\Agent\AgentStopReason;

switch ($response->stopReason) {
    case AgentStopReason::Completed:
        // Agent finished naturally
        break;
    case AgentStopReason::MaxIterations:
        // Hit iteration limit
        break;
    case AgentStopReason::ToolStopped:
        // A tool signaled to stop
        break;
    case AgentStopReason::Cancelled:
        // Manually cancelled
        break;
    case AgentStopReason::Error:
        // An error occurred
        break;
}
```

## Agent Iterations

Each iteration of the agent loop is tracked:

```php
foreach ($response->iterations as $iteration) {
    echo "Iteration {$iteration->index}\n";
    echo "Content: {$iteration->content()}\n";
    echo "Duration: {$iteration->duration}s\n";
    echo "Tokens: {$iteration->usage->totalTokens()}\n";

    if ($iteration->hasToolCalls()) {
        foreach ($iteration->toolCalls as $call) {
            echo "Called: {$call['tool']}\n";
        }
    }
}
```

## Loop Strategies

Agents can use different loop strategies:

```php
use JayI\Cortex\Plugins\Agent\AgentLoopStrategy;

$agent = Agent::make('my-agent')
    ->withLoopStrategy(AgentLoopStrategy::Simple);  // Default
```

### Available Strategies

| Strategy | Description |
|----------|-------------|
| `Simple` | Basic tool loop - calls tools until done or max iterations |
| `ReAct` | Reasoning + Acting pattern (coming soon) |
| `PlanAndExecute` | Plan first, then execute (coming soon) |
| `Custom` | Use a custom loop implementation |

### Custom Loop

```php
use JayI\Cortex\Plugins\Agent\Contracts\AgentLoopContract;

class MyCustomLoop implements AgentLoopContract
{
    public function execute(
        AgentContract $agent,
        string|array $input,
        AgentContext $context
    ): AgentResponse {
        // Custom loop logic
    }
}

$agent = Agent::make('my-agent')
    ->withCustomLoop(new MyCustomLoop());
```

## Memory Strategies

Agents can use different memory strategies to manage conversation context.

### Buffer Memory

Keeps all messages in memory:

```php
use JayI\Cortex\Plugins\Agent\Memory\BufferMemory;

$memory = new BufferMemory();

$agent = Agent::make('agent')
    ->withMemory($memory);

// Memory persists across runs
$agent->run('Hello');
$agent->run('Remember what we discussed?');
```

### Sliding Window Memory

Keeps only the most recent N messages:

```php
use JayI\Cortex\Plugins\Agent\Memory\SlidingWindowMemory;

$memory = new SlidingWindowMemory(
    windowSize: 10,            // Keep last 10 messages
    keepSystemMessage: true,   // Always keep system prompt
);
```

### Token Limit Memory

Truncates based on token count:

```php
use JayI\Cortex\Plugins\Agent\Memory\TokenLimitMemory;

$memory = new TokenLimitMemory(
    maxTokens: 4000,
    truncationStrategy: 'oldest',  // or 'middle'
);

// Set provider for token counting
$memory->setProvider($provider);
```

### Memory Contract

All memory implementations follow `MemoryContract`:

```php
interface MemoryContract
{
    public function add(Message $message): void;
    public function addMany(MessageCollection $messages): void;
    public function messages(): MessageCollection;
    public function clear(): void;
    public function tokenCount(ProviderContract $provider): int;
    public function isEmpty(): bool;
    public function count(): int;
}
```

## Agent Registry

Register and retrieve agents:

```php
use JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract;
use JayI\Cortex\Plugins\Agent\AgentCollection;

$registry = app(AgentRegistryContract::class);

// Register an agent
$registry->register($agent);

// Get an agent
$agent = $registry->get('research-assistant');

// Check existence
$registry->has('research-assistant'); // true

// List all agents (returns AgentCollection)
$agents = $registry->all();

// Get specific agents (returns AgentCollection)
$subset = $registry->only(['research-assistant', 'writing-assistant']);

// Get all except specified (returns AgentCollection)
$filtered = $registry->except(['deprecated-agent']);
```

## Hooks

The Agent plugin provides hooks for customization:

```php
$manager->addHook('agent.before_iteration', function ($agent, $iteration, $messages) {
    // Called before each loop iteration
    return $messages;
});

$manager->addHook('agent.after_iteration', function ($agent, $iteration, $messages, $response) {
    // Called after each loop iteration
    return $response;
});
```

## Error Handling

```php
use JayI\Cortex\Exceptions\AgentException;

try {
    $response = $agent->run('Do something');
} catch (AgentException $e) {
    // Agent not found
    // Run failed
    // Max iterations exceeded
    $context = $e->context();
}
```

Exception types:

```php
AgentException::notFound($id);
AgentException::runFailed($id, $message);
AgentException::maxIterationsExceeded($id, $maxIterations);
AgentException::invalidLoopStrategy($strategy);
```

### Plugin Dependency Exceptions

Certain Agent methods require specific plugins to be enabled. A `PluginException` is thrown if the required plugin is not registered:

```php
use JayI\Cortex\Exceptions\PluginException;

// Requires 'tool' plugin to be enabled
try {
    $agent = Agent::make('my-agent')
        ->withTools($tools);    // Throws if tool plugin disabled
} catch (PluginException $e) {
    // "Plugin [tool] is disabled."
}

// Requires 'mcp' plugin to be enabled
try {
    $agent = Agent::make('my-agent')
        ->withMcpServers($servers);  // Throws if mcp plugin disabled
} catch (PluginException $e) {
    // "Plugin [mcp] is disabled."
}
```

**Methods requiring `tool` plugin:**
- `withTools()`
- `addTool()`

**Methods requiring `mcp` plugin:**
- `withMcpServers()`
- `addMcpServer()`

## Configuration

```php
// config/cortex.php
'agent' => [
    // Default max iterations
    'default_max_iterations' => 10,

    // Agent discovery
    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path('Agents'),
        ],
    ],
],
```

## Complete Example

```php
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\Memory\SlidingWindowMemory;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolResult;
use JayI\Cortex\Plugins\Schema\Schema;

// Create tools
$searchTool = Tool::make('web_search')
    ->withDescription('Search the web for information')
    ->withInput(
        Schema::object()
            ->property('query', Schema::string()->description('Search query'))
            ->required('query')
    )
    ->withHandler(function (array $input) {
        $results = WebSearch::query($input['query']);
        return ToolResult::success($results);
    });

$calculatorTool = Tool::make('calculate')
    ->withDescription('Perform mathematical calculations')
    ->withInput(
        Schema::object()
            ->property('expression', Schema::string())
            ->required('expression')
    )
    ->withHandler(function (array $input) {
        $result = eval("return {$input['expression']};");
        return ToolResult::success(['result' => $result]);
    });

// Create agent
$agent = Agent::make('research-assistant')
    ->withName('Research Assistant')
    ->withSystemPrompt(<<<PROMPT
You are a research assistant that helps users find and analyze information.
Use the available tools to search for information and perform calculations.
Always cite your sources and explain your reasoning.
PROMPT)
    ->withTools([$searchTool, $calculatorTool])
    ->withMemory(new SlidingWindowMemory(windowSize: 20))
    ->withMaxIterations(15);

// Run with context
$context = new AgentContext(
    conversationId: 'research-session-001',
);

$response = $agent->run(
    'What is the population of Tokyo and how does it compare to New York?',
    $context
);

echo "Response: {$response->content}\n";
echo "Iterations: {$response->iterationCount}\n";
echo "Tools used: " . count($response->toolCalls()) . "\n";

foreach ($response->toolCalls() as $call) {
    echo "- {$call['tool']}: " . json_encode($call['input']) . "\n";
}
```

## Async Execution

Run agents asynchronously using Laravel queues.

### Running Async

```php
use JayI\Cortex\Plugins\Agent\Agent;

$agent = Agent::make('research-assistant')
    ->withSystemPrompt('You are a research assistant')
    ->withTools($tools);

// Start async execution
$pendingRun = $agent->runAsync('Research quantum computing');

// Get run ID for tracking
$runId = $pendingRun->id();
```

### Checking Status

```php
use JayI\Cortex\Plugins\Agent\AgentRunStatus;

// Check status
$status = $pendingRun->status();

if ($status === AgentRunStatus::Completed) {
    $response = $pendingRun->response();
    echo $response->content;
}

if ($status === AgentRunStatus::Failed) {
    $error = $pendingRun->error();
}
```

### AgentRunStatus Enum

```php
use JayI\Cortex\Plugins\Agent\AgentRunStatus;

AgentRunStatus::Pending;    // Queued, not started
AgentRunStatus::Running;    // Currently executing
AgentRunStatus::Completed;  // Finished successfully
AgentRunStatus::Failed;     // Encountered error

// Check if terminal state
$status->isTerminal(); // true for Completed/Failed
```

### Event-Driven Results

```php
use JayI\Cortex\Plugins\Agent\Events\AgentRunCompletedEvent;

// Listen for completion
Event::listen(AgentRunCompletedEvent::class, function ($event) {
    $runId = $event->runId;
    $response = $event->response;

    // Notify user, store results, etc.
});
```

### Queue Configuration

```php
// config/cortex.php
'agent' => [
    'async' => [
        'enabled' => true,
        'queue' => 'agents',
        'connection' => 'redis',
    ],
],
```

## Multi-Agent Orchestration

Agents can delegate tasks to other agents using the agent-as-tool pattern. This enables building hierarchical multi-agent systems where a supervisor agent orchestrates specialized sub-agents.

### Converting Agents to Tools

Any agent can be converted to a tool using the `asTool()` method:

```php
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentTool;

// Create specialized agents
$researchAgent = Agent::make('research-agent')
    ->withDescription('Specializes in web research and finding information')
    ->withSystemPrompt('You are a research specialist...')
    ->withTools([$searchTool, $webScrapeTool]);

$writingAgent = Agent::make('writing-agent')
    ->withDescription('Specializes in writing and content creation')
    ->withSystemPrompt('You are a writing specialist...')
    ->withTools([$grammarTool]);

// Convert agents to tools
$researchTool = $researchAgent->asTool();
$writingTool = $writingAgent->asTool();

// Create orchestrator with agent tools
$orchestrator = Agent::make('orchestrator')
    ->withSystemPrompt('You coordinate tasks between specialists...')
    ->withTools([$researchTool, $writingTool]);

$response = $orchestrator->run('Research AI trends and write a summary');
```

### AgentTool Class

The `AgentTool` class wraps an agent as a tool:

```php
use JayI\Cortex\Plugins\Agent\AgentTool;

$tool = AgentTool::make($agent);

// Customize the tool
$tool = AgentTool::make($agent)
    ->withName('research')                    // Custom tool name (default: agent ID)
    ->withDescription('Research specialist')  // Custom description (default: agent description)
    ->withTimeout(120);                       // Execution timeout in seconds
```

### Custom Input Schema

By default, agent tools accept a single `task` property. You can customize this:

```php
use JayI\Cortex\Plugins\Schema\Schema;

$tool = AgentTool::make($agent)
    ->withInputSchema(
        Schema::object()
            ->property('query', Schema::string()->description('The research query'))
            ->property('depth', Schema::enum(['shallow', 'deep'])->default('shallow'))
            ->required('query')
    );
```

### Converting Collections to Tools

Convert an entire collection of agents to tools:

```php
use JayI\Cortex\Plugins\Agent\AgentCollection;

$agents = AgentCollection::make([
    $researchAgent,
    $writingAgent,
    $analysisAgent,
]);

// Convert all agents to tools
$tools = $agents->asTools();

// Use in an orchestrator
$orchestrator = Agent::make('orchestrator')
    ->withTools($tools);
```

### Context Propagation

When an agent is invoked as a tool, context is automatically propagated:

```php
// Parent context
$context = new AgentContext(
    conversationId: 'conv-123',
    tenantId: 'tenant-456',
);

// When orchestrator calls a sub-agent tool, the sub-agent receives:
// - conversationId: 'conv-123' (propagated)
// - tenantId: 'tenant-456' (propagated)
// - metadata['parent_agent']: 'orchestrator' (added automatically)
// - metadata['delegated_from_tool']: true (added automatically)
```

### Tool Result

When an agent tool executes, it returns a structured result:

```php
$result = $agentTool->execute(['task' => 'Research AI'], $context);

if ($result->success) {
    $response = $result->output['response'];     // Agent's text response
    $iterations = $result->output['iterations']; // Number of iterations used
    $stopReason = $result->output['stop_reason']; // 'completed', 'max_iterations', etc.
}
```

### Multi-Agent Example

Complete example of a hierarchical multi-agent system:

```php
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolResult;
use JayI\Cortex\Plugins\Schema\Schema;

// Level 1: Specialized worker agents
$researchAgent = Agent::make('researcher')
    ->withDescription('Expert at finding and summarizing information from the web')
    ->withSystemPrompt('You are a research specialist. Find accurate information and cite sources.')
    ->withTools([
        Tool::make('web_search')
            ->withDescription('Search the web')
            ->withInput(Schema::object()->property('query', Schema::string())->required('query'))
            ->withHandler(fn ($input) => ToolResult::success(WebSearch::query($input['query']))),
    ])
    ->withMaxIterations(5);

$writerAgent = Agent::make('writer')
    ->withDescription('Expert at writing clear, engaging content')
    ->withSystemPrompt('You are a writing specialist. Create well-structured, engaging content.')
    ->withMaxIterations(3);

$reviewerAgent = Agent::make('reviewer')
    ->withDescription('Expert at reviewing and improving content quality')
    ->withSystemPrompt('You are a quality reviewer. Check for accuracy, clarity, and completeness.')
    ->withMaxIterations(3);

// Level 2: Orchestrator agent
$orchestrator = Agent::make('project-manager')
    ->withSystemPrompt(<<<PROMPT
You are a project manager coordinating a content creation team.

You have access to three specialists:
- researcher: For finding information
- writer: For creating content
- reviewer: For quality checks

For any content request:
1. First, delegate research to gather information
2. Then, have the writer create content based on research
3. Finally, have the reviewer check the quality

Synthesize their outputs into a final deliverable.
PROMPT)
    ->withTools([
        $researchAgent->asTool(),
        $writerAgent->asTool(),
        $reviewerAgent->asTool(),
    ])
    ->withMaxIterations(10);

// Execute
$response = $orchestrator->run(
    'Create a blog post about the benefits of renewable energy',
    new AgentContext(conversationId: 'blog-project-001')
);

echo $response->content;
echo "Total iterations: {$response->iterationCount}\n";
echo "Tool calls made: " . count($response->toolCalls()) . "\n";
```

### Best Practices

1. **Clear Agent Descriptions**: Write descriptive agent descriptions so the orchestrator knows when to use each specialist.

2. **Appropriate Max Iterations**: Set lower `maxIterations` for sub-agents to prevent runaway execution.

3. **Error Handling**: Agent tools automatically catch exceptions and return error results, but consider handling specific failure cases.

4. **Context Isolation**: Each sub-agent runs with its own memory by default. Use shared memory strategies for collaboration.

5. **Timeout Management**: Set appropriate timeouts for agent tools to prevent blocking:
   ```php
   $tool = $agent->asTool()->withTimeout(60);
   ```

## RAG Integration

Retrieval Augmented Generation (RAG) support for agents.

### Retriever Contract

All retrievers implement `RetrieverContract`:

```php
use JayI\Cortex\Plugins\Agent\Contracts\RetrieverContract;

interface RetrieverContract
{
    public function retrieve(string $query, int $limit = 5): RetrievedContent;
}
```

### Collection Retriever

Simple in-memory retrieval using text matching:

```php
use JayI\Cortex\Plugins\Agent\Retrievers\CollectionRetriever;

// From strings
$retriever = CollectionRetriever::fromStrings([
    'PHP is a server-side scripting language',
    'Python is great for data science',
    'JavaScript runs in browsers',
]);

// From array with metadata
$retriever = CollectionRetriever::make(collect([
    ['content' => 'Document 1', 'category' => 'tech'],
    ['content' => 'Document 2', 'category' => 'science'],
]));

$results = $retriever->retrieve('programming languages', limit: 3);
```

### Eloquent Retriever

Retrieve from database using Eloquent:

```php
use JayI\Cortex\Plugins\Agent\Retrievers\EloquentRetriever;

$retriever = EloquentRetriever::make(
    model: Document::class,
    searchColumns: ['title', 'content']
)
->contentColumn('content')
->scoreColumn('relevance')
->modifyQuery(function ($query, $searchQuery) {
    $query->where('published', true)
          ->orderByDesc('created_at');
});
```

### Callback Retriever

Custom retrieval logic:

```php
use JayI\Cortex\Plugins\Agent\Retrievers\CallbackRetriever;

$retriever = CallbackRetriever::make(function (string $query, int $limit) {
    // Call external vector database
    $results = Pinecone::query($query, $limit);

    return new RetrievedContent(
        items: array_map(fn ($r) => new RetrievedItem(
            content: $r['text'],
            score: $r['score'],
            metadata: $r['metadata'],
        ), $results)
    );
});
```

### Using Retriever with Agent

```php
use JayI\Cortex\Plugins\Agent\Agent;

$agent = Agent::make('qa-assistant')
    ->withSystemPrompt('Answer questions using the provided context')
    ->withRetriever($retriever, limit: 5);

// The agent automatically augments input with retrieved context
$response = $agent->run('What is PHP?');
```

### Retrieved Content

```php
use JayI\Cortex\Plugins\Agent\RetrievedContent;

$content = $retriever->retrieve('query');

// Check results
$content->isEmpty();
$content->count();

// Filter by score
$highQuality = $content->filterByScore(0.8);

// Sort by relevance
$sorted = $content->sortedByScore();

// Take top N
$top3 = $content->take(3);

// Convert to context string
$contextStr = $content->toContext();

// Convert to string array
$strings = $content->toStringArray();
```

### RetrievedItem

```php
use JayI\Cortex\Plugins\Agent\RetrievedItem;

$item = new RetrievedItem(
    content: 'Document content here',
    score: 0.95,
    metadata: ['source' => 'docs.pdf', 'page' => 5],
);

$item->content;     // The text content
$item->score;       // Relevance score (0-1)
$item->metadata;    // Additional metadata
$item->toContext(); // Formatted for context injection
```

### Context Augmentation

When a retriever is configured, the agent automatically:

1. Extracts the query from input
2. Retrieves relevant content
3. Augments the input with context

For string input:
```
"What is PHP?"

→ Becomes:

"What is PHP?

---
Relevant Context:
PHP is a server-side scripting language...
..."
```

For array input:
```php
[
    'query' => 'What is PHP?',
    '_retrieved_context' => '...',  // Added automatically
    '_retrieved_items' => [...],    // Added automatically
]
```

### RAG Complete Example

```php
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\Retrievers\CallbackRetriever;
use JayI\Cortex\Plugins\Agent\RetrievedContent;
use JayI\Cortex\Plugins\Agent\RetrievedItem;

// Create a retriever that queries your vector database
$retriever = CallbackRetriever::make(function (string $query, int $limit) {
    $embeddings = OpenAI::embeddings()->create([
        'model' => 'text-embedding-3-small',
        'input' => $query,
    ]);

    $results = Pinecone::index('docs')->query([
        'vector' => $embeddings['data'][0]['embedding'],
        'topK' => $limit,
        'includeMetadata' => true,
    ]);

    return new RetrievedContent(
        array_map(fn ($match) => new RetrievedItem(
            content: $match['metadata']['text'],
            score: $match['score'],
            metadata: $match['metadata'],
        ), $results['matches'])
    );
});

// Create RAG-enabled agent
$agent = Agent::make('documentation-assistant')
    ->withSystemPrompt(<<<PROMPT
You are a documentation assistant. Answer questions based on the provided context.
If the context doesn't contain relevant information, say so.
Always cite the source when possible.
PROMPT)
    ->withRetriever($retriever, limit: 5)
    ->withMaxIterations(3);

// Run query - context is automatically retrieved and injected
$response = $agent->run('How do I configure authentication?');

echo $response->content;
```
