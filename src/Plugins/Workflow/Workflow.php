<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use Closure;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowExecutorContract;
use JayI\Cortex\Plugins\Workflow\Nodes\AgentNode;
use JayI\Cortex\Plugins\Workflow\Nodes\CallbackNode;
use JayI\Cortex\Plugins\Workflow\Nodes\ConditionNode;
use JayI\Cortex\Plugins\Workflow\Nodes\HumanInputNode;
use JayI\Cortex\Plugins\Workflow\Nodes\LoopNode;
use JayI\Cortex\Plugins\Workflow\Nodes\ParallelNode;
use JayI\Cortex\Plugins\Workflow\Nodes\SubWorkflowNode;
use JayI\Cortex\Plugins\Workflow\Nodes\ToolNode;
use JayI\Cortex\Support\Concerns\RequiresPlugins;

/**
 * Fluent builder for workflows.
 */
class Workflow implements WorkflowContract
{
    use RequiresPlugins;

    protected string $id;

    protected string $name;

    protected string $description = '';

    /** @var array<string, NodeContract> */
    protected array $nodes = [];

    /** @var array<string, Edge> */
    protected array $edges = [];

    protected ?string $entryNode = null;

    protected ?WorkflowExecutorContract $executor = null;

    /** @var array<string, mixed> */
    protected array $metadata = [];

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->name = $id;
    }

    /**
     * Create a new workflow builder.
     */
    public static function make(string $id): static
    {
        return new static($id);
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Set the workflow name.
     */
    public function withName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Set the workflow description.
     */
    public function withDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the workflow executor.
     */
    public function executor(WorkflowExecutorContract $executor): static
    {
        $this->executor = $executor;

        return $this;
    }

    /**
     * Add metadata to the workflow.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Add a node to the workflow.
     */
    public function addNode(NodeContract $node): static
    {
        $this->nodes[$node->id()] = $node;

        // Set as entry node if first node
        if ($this->entryNode === null) {
            $this->entryNode = $node->id();
        }

        return $this;
    }

    /**
     * Add a callback node.
     */
    public function callback(string $nodeId, Closure $callback): static
    {
        return $this->addNode(new CallbackNode($nodeId, $callback));
    }

    /**
     * Add a condition node with branches.
     *
     * @param  array<string, string>  $branches
     */
    public function condition(string $nodeId, Closure $condition, array $branches = []): static
    {
        return $this->addNode(new ConditionNode($nodeId, $condition, $branches));
    }

    /**
     * Add an agent node.
     *
     * @param  array<string, mixed>|Closure  $inputMapping
     *
     * @throws \JayI\Cortex\Exceptions\PluginException
     */
    public function agent(
        string $nodeId,
        AgentContract|string $agent,
        array|Closure $inputMapping = [],
        ?string $outputKey = null
    ): static {
        $this->ensurePluginEnabled('agent');

        return $this->addNode(new AgentNode($nodeId, $agent, $inputMapping, $outputKey));
    }

    /**
     * Add a tool node.
     *
     * @param  array<string, mixed>|Closure  $inputMapping
     *
     * @throws \JayI\Cortex\Exceptions\PluginException
     */
    public function tool(
        string $nodeId,
        ToolContract|string $tool,
        array|Closure $inputMapping = [],
        ?string $outputKey = null
    ): static {
        $this->ensurePluginEnabled('tool');

        return $this->addNode(new ToolNode($nodeId, $tool, $inputMapping, $outputKey));
    }

    /**
     * Add a loop node.
     */
    public function loop(
        string $nodeId,
        NodeContract $body,
        Closure $condition,
        int $maxIterations = 100
    ): static {
        return $this->addNode(new LoopNode($nodeId, $body, $condition, $maxIterations));
    }

    /**
     * Add a parallel node.
     *
     * @param  array<int, NodeContract>  $nodes
     */
    public function parallel(
        string $nodeId,
        array $nodes,
        string $mergeStrategy = 'all',
        ?Closure $merger = null
    ): static {
        return $this->addNode(new ParallelNode($nodeId, $nodes, $mergeStrategy, $merger));
    }

    /**
     * Add a human input node.
     */
    public function humanInput(
        string $nodeId,
        string $prompt,
        ?Schema $inputSchema = null,
        ?int $timeout = null
    ): static {
        return $this->addNode(new HumanInputNode($nodeId, $prompt, $inputSchema, $timeout));
    }

    /**
     * Add a sub-workflow node.
     *
     * @param  array<string, mixed>|Closure  $inputMapping
     */
    public function subWorkflow(
        string $nodeId,
        WorkflowContract|string $workflow,
        array|Closure $inputMapping = [],
        ?string $outputKey = null
    ): static {
        return $this->addNode(new SubWorkflowNode($nodeId, $workflow, $inputMapping, $outputKey));
    }

    /**
     * Set the entry node.
     */
    public function entry(string $nodeId): static
    {
        $this->entryNode = $nodeId;

        return $this;
    }

    /**
     * Add an edge between nodes.
     */
    public function edge(string $from, string $to, ?Closure $condition = null, int $priority = 0): static
    {
        $edgeId = "{$from}->{$to}";

        $this->edges[$edgeId] = Edge::start($from)->to($to)->when($condition)->priority($priority)->build();

        return $this;
    }

    /**
     * Add a simple transition from one node to the next.
     */
    public function then(string $from, string $to): static
    {
        return $this->edge($from, $to);
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function definition(): WorkflowDefinition
    {
        return new WorkflowDefinition(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            nodes: array_values($this->nodes),
            edges: array_values($this->edges),
            entryNode: $this->entryNode,
            metadata: $this->metadata,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function run(array $input = [], ?WorkflowContext $context = null): WorkflowResult
    {
        $executor = $this->resolveExecutor();

        return $executor->execute($this, $input, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(WorkflowState $state, array $input = []): WorkflowResult
    {
        $executor = $this->resolveExecutor();

        return $executor->resume($this, $state, $input);
    }

    /**
     * Get a node by ID.
     */
    public function getNode(string $nodeId): ?NodeContract
    {
        return $this->nodes[$nodeId] ?? null;
    }

    /**
     * Get edges from a node.
     *
     * @return array<Edge>
     */
    public function getEdgesFrom(string $nodeId): array
    {
        return array_values(array_filter(
            $this->edges,
            fn (Edge $edge) => $edge->from === $nodeId
        ));
    }

    /**
     * Resolve the executor instance.
     */
    protected function resolveExecutor(): WorkflowExecutorContract
    {
        if ($this->executor !== null) {
            return $this->executor;
        }

        return app(WorkflowExecutorContract::class);
    }
}
