<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Loops;

use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Events\Agent\AgentIterationCompleted;
use JayI\Cortex\Events\Agent\AgentIterationStarted;
use JayI\Cortex\Events\Agent\AgentMaxIterationsReached;
use JayI\Cortex\Events\Agent\AgentRunCompleted;
use JayI\Cortex\Events\Agent\AgentRunFailed;
use JayI\Cortex\Events\Agent\AgentRunStarted;
use JayI\Cortex\Events\Agent\AgentToolCalled;
use JayI\Cortex\Events\Concerns\DispatchesCortexEvents;
use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\AgentIteration;
use JayI\Cortex\Plugins\Agent\AgentResponse;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentLoopContract;
use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolExecutor;

/**
 * Simple agent loop that executes tools until completion or max iterations.
 */
class SimpleAgentLoop implements AgentLoopContract
{
    use DispatchesCortexEvents;

    public function __construct(
        protected ChatClientContract $chatClient,
        protected ToolExecutor $toolExecutor,
        protected PluginManagerContract $pluginManager,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function execute(
        AgentContract $agent,
        string|array $input,
        AgentContext $context
    ): AgentResponse {
        $messages = $this->buildInitialMessages($agent, $input, $context);
        $iterations = [];
        $totalUsage = Usage::zero();
        $maxIterations = $agent->maxIterations();

        $this->dispatchCortexEvent(new AgentRunStarted(
            agent: $agent,
            input: $input,
        ));

        try {
            for ($i = 0; $i < $maxIterations; $i++) {
                // Apply before iteration hook
                $this->pluginManager->applyHooks('agent.before_iteration', $agent, $i, $messages);

                $this->dispatchCortexEvent(new AgentIterationStarted(
                    agent: $agent,
                    iteration: $i,
                    state: ['messages_count' => $messages->count()],
                ));

                $startTime = microtime(true);

                // Send request
                $request = $this->buildRequest($agent, $messages);
                $client = $agent->provider() !== null
                    ? $this->chatClient->using($agent->provider())
                    : $this->chatClient;
                $response = $client->send($request);

                $duration = microtime(true) - $startTime;
                $totalUsage = $totalUsage->add($response->usage);

                // Add assistant response to messages
                $messages->add($response->message);

                // Check if we need to execute tools
                $toolCalls = [];
                if ($response->hasToolCalls()) {
                    $toolCallResults = $this->executeToolCalls($agent, $response, $context);
                    $toolCalls = $toolCallResults['calls'];

                    // Check if any tool signaled to stop
                    if ($toolCallResults['shouldStop']) {
                        $iterations[] = new AgentIteration(
                            index: $i,
                            response: $response,
                            toolCalls: $toolCalls,
                            usage: $response->usage,
                            duration: $duration,
                        );

                        // Apply after iteration hook
                        $this->pluginManager->applyHooks('agent.after_iteration', $agent, $i, $messages, $response);

                        $this->dispatchCortexEvent(new AgentIterationCompleted(
                            agent: $agent,
                            iteration: $i,
                            state: ['messages_count' => $messages->count()],
                            response: $response,
                        ));

                        $agentResponse = AgentResponse::toolStopped(
                            content: $toolCallResults['stopOutput'] ?? $response->content(),
                            messages: $messages,
                            iterations: $iterations,
                            totalUsage: $totalUsage,
                            finalResponse: $response,
                        );

                        $this->dispatchCortexEvent(new AgentRunCompleted(
                            agent: $agent,
                            input: $input,
                            output: $agentResponse->content,
                            iterations: count($iterations),
                        ));

                        return $agentResponse;
                    }

                    // Add tool results to messages
                    foreach ($toolCallResults['results'] as $result) {
                        $messages->add($result);
                    }
                }

                $iterations[] = new AgentIteration(
                    index: $i,
                    response: $response,
                    toolCalls: $toolCalls,
                    usage: $response->usage,
                    duration: $duration,
                );

                // Apply after iteration hook
                $this->pluginManager->applyHooks('agent.after_iteration', $agent, $i, $messages, $response);

                $this->dispatchCortexEvent(new AgentIterationCompleted(
                    agent: $agent,
                    iteration: $i,
                    state: ['messages_count' => $messages->count()],
                    response: $response,
                ));

                // If no tool calls, we're done
                if (! $response->hasToolCalls()) {
                    // Add messages to memory if configured
                    if ($agent->memory() !== null) {
                        $agent->memory()->addMany($messages);
                    }

                    $agentResponse = AgentResponse::success(
                        content: $response->content(),
                        messages: $messages,
                        iterations: $iterations,
                        totalUsage: $totalUsage,
                        finalResponse: $response,
                    );

                    $this->dispatchCortexEvent(new AgentRunCompleted(
                        agent: $agent,
                        input: $input,
                        output: $agentResponse->content,
                        iterations: count($iterations),
                    ));

                    return $agentResponse;
                }
            }

            // Hit max iterations
            $lastResponse = $iterations[count($iterations) - 1]->response ?? null;

            $this->dispatchCortexEvent(new AgentMaxIterationsReached(
                agent: $agent,
                state: ['messages_count' => $messages->count()],
                maxIterations: $maxIterations,
            ));

            $agentResponse = AgentResponse::maxIterations(
                content: $lastResponse?->content() ?? '',
                messages: $messages,
                iterations: $iterations,
                totalUsage: $totalUsage,
                finalResponse: $lastResponse,
            );

            $this->dispatchCortexEvent(new AgentRunCompleted(
                agent: $agent,
                input: $input,
                output: $agentResponse->content,
                iterations: count($iterations),
            ));

            return $agentResponse;
        } catch (\Throwable $e) {
            $this->dispatchCortexEvent(new AgentRunFailed(
                agent: $agent,
                input: $input,
                exception: $e,
                iterations: count($iterations),
            ));

            throw $e;
        }
    }

    /**
     * Build initial messages for the conversation.
     *
     * @param  string|array<string, mixed>  $input
     */
    protected function buildInitialMessages(
        AgentContract $agent,
        string|array $input,
        AgentContext $context
    ): MessageCollection {
        $messages = MessageCollection::make();

        // Add system prompt
        $messages->system($agent->systemPrompt());

        // Add history from context if available
        if ($context->history !== null) {
            foreach ($context->history->withoutSystem() as $message) {
                $messages->add($message);
            }
        }

        // Add history from memory if available
        if ($agent->memory() !== null && ! $agent->memory()->isEmpty()) {
            foreach ($agent->memory()->messages()->withoutSystem() as $message) {
                $messages->add($message);
            }
        }

        // Add user input
        $userMessage = is_string($input) ? $input : json_encode($input);
        $messages->user($userMessage);

        return $messages;
    }

    /**
     * Build a chat request.
     */
    protected function buildRequest(AgentContract $agent, MessageCollection $messages): ChatRequest
    {
        return new ChatRequest(
            messages: $messages,
            systemPrompt: null, // Already in messages
            model: $agent->model(),
            tools: $agent->tools()->isEmpty() ? null : $agent->tools()->toChatToolCollection(),
        );
    }

    /**
     * Execute tool calls from the response.
     *
     * @return array{calls: array<int, array{tool: string, input: array<string, mixed>, output: mixed}>, results: array<int, Message>, shouldStop: bool, stopOutput: ?string}
     */
    protected function executeToolCalls(
        AgentContract $agent,
        \JayI\Cortex\Plugins\Chat\ChatResponse $response,
        AgentContext $context
    ): array {
        $calls = [];
        $results = [];
        $shouldStop = false;
        $stopOutput = null;

        $toolContext = new ToolContext(
            conversationId: $context->conversationId,
            agentId: $agent->id(),
            tenantId: $context->tenantId,
            metadata: $context->metadata,
        );

        foreach ($response->toolCalls() as $toolCall) {
            $tool = $agent->tools()->find($toolCall->name);

            if ($tool === null) {
                $results[] = Message::toolResult(
                    $toolCall->id,
                    ['error' => "Tool '{$toolCall->name}' not found"],
                    isError: true
                );
                $calls[] = [
                    'tool' => $toolCall->name,
                    'input' => $toolCall->input,
                    'output' => ['error' => 'Tool not found'],
                ];

                continue;
            }

            $this->dispatchCortexEvent(new AgentToolCalled(
                agent: $agent,
                tool: $tool,
                input: $toolCall->input,
            ));

            $result = $this->toolExecutor->executeTool($tool, $toolCall->input, $toolContext);

            $calls[] = [
                'tool' => $toolCall->name,
                'input' => $toolCall->input,
                'output' => $result->output,
            ];

            $results[] = Message::toolResult(
                $toolCall->id,
                $result->success ? $result->output : $result->error,
                isError: ! $result->success
            );

            if ($result->shouldStop()) {
                $shouldStop = true;
                $stopOutput = $result->toContentString();
            }
        }

        return [
            'calls' => $calls,
            'results' => $results,
            'shouldStop' => $shouldStop,
            'stopOutput' => $stopOutput,
        ];
    }
}
