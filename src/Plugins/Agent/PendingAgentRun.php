<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use JayI\Cortex\Events\Agent\AgentRunCompleted;
use JayI\Cortex\Events\Agent\AgentRunFailed;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract;
use JayI\Cortex\Plugins\Agent\Events\AgentRunCompletedEvent;

class PendingAgentRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $runId;

    protected ?string $broadcastChannel = null;

    /**
     * @param  string|array<string, mixed>  $input
     */
    public function __construct(
        public AgentContract|string $agent,
        public string|array $input,
        public ?AgentContext $context = null,
    ) {
        $this->runId = Str::uuid()->toString();
        $this->queue = config('cortex.agent.async.queue', 'default');
    }

    /**
     * Get the run ID.
     */
    public function id(): string
    {
        return $this->runId;
    }

    /**
     * Set the queue for this job.
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Set the broadcast channel for completion notification.
     */
    public function broadcastTo(string $channel): static
    {
        $this->broadcastChannel = $channel;

        return $this;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->updateStatus(AgentRunStatus::Running);

        try {
            $agent = $this->resolveAgent();
            $response = $agent->run($this->input, $this->context);

            $this->storeResult($response);
            $this->updateStatus(AgentRunStatus::Completed);

            event(new AgentRunCompleted(
                agent: $agent,
                input: $this->input,
                output: $response->content,
                iterations: $response->iterationCount,
            ));

            if ($this->broadcastChannel) {
                broadcast(new AgentRunCompletedEvent(
                    $this->broadcastChannel,
                    $this->runId,
                    $response,
                ));
            }
        } catch (\Throwable $e) {
            $this->updateStatus(AgentRunStatus::Failed, $e->getMessage());

            event(new AgentRunFailed(
                agent: $this->agent,
                input: $this->input,
                exception: $e,
                iterations: 0,
            ));

            throw $e;
        }
    }

    /**
     * Resolve the agent from ID or return the instance.
     */
    protected function resolveAgent(): AgentContract
    {
        if ($this->agent instanceof AgentContract) {
            return $this->agent;
        }

        return app(AgentRegistryContract::class)->get($this->agent);
    }

    /**
     * Update the run status in cache.
     */
    protected function updateStatus(AgentRunStatus $status, ?string $error = null): void
    {
        Cache::put("cortex:agent_run:{$this->runId}:status", [
            'status' => $status->value,
            'error' => $error,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(24));
    }

    /**
     * Store the result in cache.
     */
    protected function storeResult(AgentResponse $response): void
    {
        Cache::put("cortex:agent_run:{$this->runId}:result", $response, now()->addHours(24));
    }

    /**
     * Get the status of a run.
     */
    public static function status(string $runId): AgentRunStatus
    {
        $data = Cache::get("cortex:agent_run:{$runId}:status");

        if (! $data) {
            return AgentRunStatus::Pending;
        }

        return AgentRunStatus::from($data['status']);
    }

    /**
     * Get the error message if the run failed.
     */
    public static function error(string $runId): ?string
    {
        $data = Cache::get("cortex:agent_run:{$runId}:status");

        return $data['error'] ?? null;
    }

    /**
     * Get the result of a completed run.
     */
    public static function result(string $runId): ?AgentResponse
    {
        return Cache::get("cortex:agent_run:{$runId}:result");
    }

    /**
     * Wait for the run to complete and return the result.
     */
    public static function await(string $runId, int $timeout = 300): ?AgentResponse
    {
        $start = time();

        while (time() - $start < $timeout) {
            $status = self::status($runId);

            if ($status->isComplete()) {
                return self::result($runId);
            }

            usleep(100000); // 100ms
        }

        return null;
    }
}
