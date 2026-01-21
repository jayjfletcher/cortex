<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool;

use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Events\Concerns\DispatchesCortexEvents;
use JayI\Cortex\Events\Tool\AfterToolExecution;
use JayI\Cortex\Events\Tool\BeforeToolExecution;
use JayI\Cortex\Events\Tool\ToolError;
use JayI\Cortex\Exceptions\ToolException;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Plugins\Tool\Contracts\ToolRegistryContract;

class ToolExecutor
{
    use DispatchesCortexEvents;

    public function __construct(
        protected ToolRegistryContract $registry,
        protected ?PluginManagerContract $pluginManager = null,
    ) {}

    /**
     * Execute a tool by name.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(string $name, array $input, ?ToolContext $context = null): ToolResult
    {
        $tool = $this->registry->get($name);
        $context ??= new ToolContext;

        return $this->executeTool($tool, $input, $context);
    }

    /**
     * Execute a tool instance.
     *
     * @param  array<string, mixed>  $input
     */
    public function executeTool(ToolContract $tool, array $input, ?ToolContext $context = null): ToolResult
    {
        $context ??= new ToolContext;

        // Validate input
        $validation = $tool->inputSchema()->validate($input);
        if (! $validation->isValid()) {
            return ToolResult::error(
                'Input validation failed: '.implode(', ', array_map(
                    fn ($e) => $e->message,
                    $validation->errors
                ))
            );
        }

        // Apply before hooks
        if ($this->pluginManager !== null) {
            $input = $this->pluginManager->applyHooks('tool.before_execute', $input, $tool, $context);
        }

        $this->dispatchCortexEvent(new BeforeToolExecution(
            tool: $tool,
            input: $input,
            context: $context->metadata,
        ));

        $startTime = microtime(true);

        try {
            // Execute with timeout if specified
            $timeout = $tool->timeout();
            if ($timeout !== null) {
                $result = $this->executeWithTimeout($tool, $input, $context, $timeout);
            } else {
                $result = $tool->execute($input, $context);
            }

            $duration = microtime(true) - $startTime;

            // Apply after hooks
            if ($this->pluginManager !== null) {
                $result = $this->pluginManager->applyHooks('tool.after_execute', $result, $tool, $input, $context);
            }

            $this->dispatchCortexEvent(new AfterToolExecution(
                tool: $tool,
                input: $input,
                output: $result,
                duration: $duration,
            ));

            return $result;
        } catch (ToolException $e) {
            $this->dispatchCortexEvent(new ToolError(
                tool: $tool,
                input: $input,
                exception: $e,
            ));

            throw $e;
        } catch (\Throwable $e) {
            $this->dispatchCortexEvent(new ToolError(
                tool: $tool,
                input: $input,
                exception: $e,
            ));

            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Execute multiple tools in sequence.
     *
     * @param  array<int, array{name: string, input: array<string, mixed>}>  $toolCalls
     * @return array<int, ToolResult>
     */
    public function executeMany(array $toolCalls, ?ToolContext $context = null): array
    {
        $context ??= new ToolContext;
        $results = [];

        foreach ($toolCalls as $call) {
            $results[] = $this->execute($call['name'], $call['input'], $context);
        }

        return $results;
    }

    /**
     * Execute a tool with timeout.
     *
     * @param  array<string, mixed>  $input
     */
    protected function executeWithTimeout(
        ToolContract $tool,
        array $input,
        ToolContext $context,
        int $timeout
    ): ToolResult {
        // Set up SIGALRM handler if pcntl is available
        if (function_exists('pcntl_signal') && function_exists('pcntl_alarm')) {
            $timedOut = false;

            // Save existing handler
            $oldHandler = pcntl_signal_get_handler(SIGALRM);

            pcntl_signal(SIGALRM, function () use (&$timedOut) {
                $timedOut = true;
            });

            pcntl_alarm($timeout);

            try {
                $result = $tool->execute($input, $context);

                pcntl_alarm(0); // Cancel alarm

                if ($timedOut) {
                    throw ToolException::timeout($tool->name(), $timeout);
                }

                return $result;
            } finally {
                // Restore old handler
                if ($oldHandler !== null) {
                    pcntl_signal(SIGALRM, $oldHandler);
                } else {
                    pcntl_signal(SIGALRM, SIG_DFL);
                }
            }
        }

        // Fallback: just execute without timeout protection
        return $tool->execute($input, $context);
    }
}
