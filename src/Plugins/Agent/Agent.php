<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentLoopContract;
use JayI\Cortex\Plugins\Agent\Contracts\RetrieverContract;
use JayI\Cortex\Plugins\Agent\Loops\SimpleAgentLoop;
use JayI\Cortex\Plugins\Agent\Memory\MemoryContract;
use JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract;
use JayI\Cortex\Plugins\Mcp\McpServerCollection;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use JayI\Cortex\Support\Concerns\RequiresPlugins;

/**
 * Fluent builder for creating agents.
 */
class Agent implements AgentContract
{
    use RequiresPlugins;

    protected string $agentId;

    protected string $agentName = '';

    protected string $agentDescription = '';

    protected string $agentSystemPrompt = '';

    protected ToolCollection $agentTools;

    protected McpServerCollection $agentMcpServers;

    protected ?string $agentModel = null;

    protected ?string $agentProvider = null;

    protected int $agentMaxIterations = 10;

    protected ?MemoryContract $agentMemory = null;

    protected AgentLoopStrategy $loopStrategy = AgentLoopStrategy::Simple;

    protected ?AgentLoopContract $customLoop = null;

    protected ?RetrieverContract $retriever = null;

    protected int $retrieverLimit = 5;

    public function __construct()
    {
        $this->agentTools = ToolCollection::make([]);
        $this->agentMcpServers = McpServerCollection::make([]);
    }

    /**
     * Create a new agent builder.
     */
    public static function make(string $id): static
    {
        $agent = new static;
        $agent->agentId = $id;

        return $agent;
    }

    /**
     * Set the agent name.
     */
    public function withName(string $name): static
    {
        $this->agentName = $name;

        return $this;
    }

    /**
     * Set the agent description.
     */
    public function withDescription(string $description): static
    {
        $this->agentDescription = $description;

        return $this;
    }

    /**
     * Set the system prompt.
     */
    public function withSystemPrompt(string $systemPrompt): static
    {
        $this->agentSystemPrompt = $systemPrompt;

        return $this;
    }

    /**
     * Set the tools.
     *
     * @param  array<int, Tool>|ToolCollection  $tools
     *
     * @throws \JayI\Cortex\Exceptions\PluginException
     */
    public function withTools(array|ToolCollection $tools): static
    {
        $this->ensurePluginEnabled('tool');

        if (is_array($tools)) {
            $this->agentTools = ToolCollection::make($tools);
        } else {
            $this->agentTools = $tools;
        }

        return $this;
    }

    /**
     * Add a tool.
     *
     * @throws \JayI\Cortex\Exceptions\PluginException
     */
    public function addTool(Tool $tool): static
    {
        $this->ensurePluginEnabled('tool');

        $this->agentTools = $this->agentTools->add($tool);

        return $this;
    }

    /**
     * Set the MCP servers.
     *
     * @param  array<int, McpServerContract|string>|McpServerCollection  $servers
     *
     * @throws \JayI\Cortex\Exceptions\PluginException
     */
    public function withMcpServers(array|McpServerCollection $servers): static
    {
        $this->ensurePluginEnabled('mcp');

        if (is_array($servers)) {
            $this->agentMcpServers = McpServerCollection::make($servers);
        } else {
            $this->agentMcpServers = $servers;
        }

        return $this;
    }

    /**
     * Add an MCP server.
     *
     * @throws \JayI\Cortex\Exceptions\PluginException
     */
    public function addMcpServer(McpServerContract|string $server): static
    {
        $this->ensurePluginEnabled('mcp');

        $this->agentMcpServers = $this->agentMcpServers->add($server);

        return $this;
    }

    /**
     * Set the model.
     */
    public function withModel(string $model): static
    {
        $this->agentModel = $model;

        return $this;
    }

    /**
     * Set the provider.
     */
    public function withProvider(string $provider): static
    {
        $this->agentProvider = $provider;

        return $this;
    }

    /**
     * Set the max iterations.
     */
    public function withMaxIterations(int $maxIterations): static
    {
        $this->agentMaxIterations = $maxIterations;

        return $this;
    }

    /**
     * Set the memory strategy.
     */
    public function withMemory(MemoryContract $memory): static
    {
        $this->agentMemory = $memory;

        return $this;
    }

    /**
     * Set the loop strategy.
     */
    public function withLoopStrategy(AgentLoopStrategy $strategy): static
    {
        $this->loopStrategy = $strategy;

        return $this;
    }

    /**
     * Set a custom loop implementation.
     */
    public function withCustomLoop(AgentLoopContract $loop): static
    {
        $this->customLoop = $loop;
        $this->loopStrategy = AgentLoopStrategy::Custom;

        return $this;
    }

    /**
     * Set a retriever for RAG (Retrieval Augmented Generation).
     */
    public function withRetriever(RetrieverContract $retriever, int $limit = 5): static
    {
        $this->retriever = $retriever;
        $this->retrieverLimit = $limit;

        return $this;
    }

    /**
     * Get the configured retriever.
     */
    public function retriever(): ?RetrieverContract
    {
        return $this->retriever;
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return $this->agentId;
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return $this->agentName ?: $this->agentId;
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return $this->agentDescription;
    }

    /**
     * {@inheritdoc}
     */
    public function systemPrompt(): string
    {
        return $this->agentSystemPrompt;
    }

    /**
     * {@inheritdoc}
     */
    public function tools(): ToolCollection
    {
        return $this->agentTools;
    }

    /**
     * {@inheritdoc}
     */
    public function mcpServers(): McpServerCollection
    {
        return $this->agentMcpServers;
    }

    /**
     * {@inheritdoc}
     */
    public function model(): ?string
    {
        return $this->agentModel;
    }

    /**
     * {@inheritdoc}
     */
    public function provider(): ?string
    {
        return $this->agentProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function maxIterations(): int
    {
        return $this->agentMaxIterations;
    }

    /**
     * {@inheritdoc}
     */
    public function memory(): ?MemoryContract
    {
        return $this->agentMemory;
    }

    /**
     * {@inheritdoc}
     */
    public function run(string|array $input, ?AgentContext $context = null): AgentResponse
    {
        $context ??= new AgentContext;
        $loop = $this->resolveLoop();

        // Augment input with retrieved context if retriever is configured
        $augmentedInput = $this->augmentWithContext($input);

        return $loop->execute($this, $augmentedInput, $context);
    }

    /**
     * Augment the input with retrieved context.
     *
     * @param  string|array<string, mixed>  $input
     * @return string|array<string, mixed>
     */
    protected function augmentWithContext(string|array $input): string|array
    {
        if ($this->retriever === null) {
            return $input;
        }

        // Extract query from input
        $query = is_string($input) ? $input : ($input['query'] ?? $input['input'] ?? json_encode($input));

        // Retrieve relevant content
        $retrieved = $this->retriever->retrieve($query, $this->retrieverLimit);

        if ($retrieved->isEmpty()) {
            return $input;
        }

        // Format the context
        $contextStr = $retrieved->toContext();

        if (is_string($input)) {
            return "{$input}\n\n---\nRelevant Context:\n{$contextStr}";
        }

        // For array input, add context to a special key
        $input['_retrieved_context'] = $contextStr;
        $input['_retrieved_items'] = $retrieved->items;

        return $input;
    }

    /**
     * {@inheritdoc}
     */
    public function stream(string|array $input, ?AgentContext $context = null): AgentResponse
    {
        // For now, streaming just uses the regular run
        // A full streaming implementation would yield chunks
        return $this->run($input, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function runAsync(string|array $input, ?AgentContext $context = null): PendingAgentRun
    {
        $pendingRun = new PendingAgentRun($this, $input, $context);
        PendingAgentRun::dispatch($this, $input, $context);

        return $pendingRun;
    }

    /**
     * Convert this agent to a tool that can be used by other agents.
     */
    public function asTool(): AgentTool
    {
        return AgentTool::make($this);
    }

    /**
     * Resolve the agent loop implementation.
     */
    protected function resolveLoop(): AgentLoopContract
    {
        if ($this->customLoop !== null) {
            return $this->customLoop;
        }

        /** @var Container $container */
        $container = app();

        return match ($this->loopStrategy) {
            AgentLoopStrategy::Simple => $container->make(SimpleAgentLoop::class),
            AgentLoopStrategy::ReAct => $container->make(SimpleAgentLoop::class), // TODO: Implement ReAct
            AgentLoopStrategy::PlanAndExecute => $container->make(SimpleAgentLoop::class), // TODO: Implement
            AgentLoopStrategy::Custom => throw new \RuntimeException('Custom loop not set'),
        };
    }
}
