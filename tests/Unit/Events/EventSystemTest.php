<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Events\Concerns\DispatchesCortexEvents;
use JayI\Cortex\Events\Provider\ProviderRegistered;
use JayI\Cortex\Events\Provider\BeforeProviderRequest;
use JayI\Cortex\Events\Provider\AfterProviderResponse;
use JayI\Cortex\Events\Provider\ProviderError;
use JayI\Cortex\Events\Provider\ProviderRateLimited;
use JayI\Cortex\Events\Chat\BeforeChatSend;
use JayI\Cortex\Events\Chat\AfterChatReceive;
use JayI\Cortex\Events\Chat\ChatError;
use JayI\Cortex\Events\Chat\ChatStreamStarted;
use JayI\Cortex\Events\Tool\ToolRegistered;
use JayI\Cortex\Events\Tool\BeforeToolExecution;
use JayI\Cortex\Events\Tool\AfterToolExecution;
use JayI\Cortex\Events\Tool\ToolError;
use JayI\Cortex\Events\Agent\AgentRunStarted;
use JayI\Cortex\Events\Agent\AgentIterationStarted;
use JayI\Cortex\Events\Agent\AgentIterationCompleted;
use JayI\Cortex\Events\Agent\AgentToolCalled;
use JayI\Cortex\Events\Agent\AgentRunCompleted;
use JayI\Cortex\Events\Agent\AgentRunFailed;
use JayI\Cortex\Events\Agent\AgentMaxIterationsReached;
use JayI\Cortex\Events\Workflow\WorkflowStarted;
use JayI\Cortex\Events\Workflow\WorkflowCompleted;
use JayI\Cortex\Events\Workflow\WorkflowFailed;
use JayI\Cortex\Events\Workflow\WorkflowPaused;
use JayI\Cortex\Events\Workflow\WorkflowResumed;
use JayI\Cortex\Events\Workflow\WorkflowNodeEntered;
use JayI\Cortex\Events\Workflow\WorkflowNodeExited;
use JayI\Cortex\Events\Guardrail\GuardrailChecked;
use JayI\Cortex\Events\Guardrail\GuardrailBlocked;

describe('CortexEvent Base Class', function () {
    it('creates event with timestamp', function () {
        $event = new class extends CortexEvent {};

        expect($event->timestamp)->toBeFloat();
        expect($event->timestamp)->toBeGreaterThan(0);
    });

    it('creates event with tenant id', function () {
        $event = new class('tenant-123') extends CortexEvent {
            public function __construct(?string $tenantId = null)
            {
                parent::__construct($tenantId);
            }
        };

        expect($event->tenantId)->toBe('tenant-123');
    });

    it('creates event with correlation id', function () {
        $event = new class(null, 'corr-123') extends CortexEvent {
            public function __construct(?string $tenantId = null, ?string $correlationId = null)
            {
                parent::__construct($tenantId, $correlationId);
            }
        };

        expect($event->correlationId)->toBe('corr-123');
    });

    it('creates event with metadata', function () {
        $event = new class(null, null, ['key' => 'value']) extends CortexEvent {
            public function __construct(?string $tenantId = null, ?string $correlationId = null, array $metadata = [])
            {
                parent::__construct($tenantId, $correlationId, $metadata);
            }
        };

        expect($event->metadata)->toBe(['key' => 'value']);
    });
});

describe('DispatchesCortexEvents Trait', function () {
    beforeEach(function () {
        Event::fake();
        Config::set('cortex.events.enabled', true);
        Config::set('cortex.events.disabled', []);
    });

    it('dispatches events when enabled', function () {
        $dispatcher = new class {
            use DispatchesCortexEvents;

            public function dispatch(CortexEvent $event): void
            {
                $this->dispatchCortexEvent($event);
            }
        };

        $event = Mockery::mock(CortexEvent::class);
        $dispatcher->dispatch($event);

        Event::assertDispatched(get_class($event));
    });

    it('does not dispatch when events are disabled', function () {
        Config::set('cortex.events.enabled', false);

        $dispatcher = new class {
            use DispatchesCortexEvents;

            public function dispatch(CortexEvent $event): void
            {
                $this->dispatchCortexEvent($event);
            }
        };

        $event = Mockery::mock(CortexEvent::class);
        $dispatcher->dispatch($event);

        Event::assertNotDispatched(get_class($event));
    });

    it('does not dispatch specific disabled events', function () {
        Config::set('cortex.events.disabled', [BeforeChatSend::class]);

        $dispatcher = new class {
            use DispatchesCortexEvents;

            public function dispatch(CortexEvent $event): void
            {
                $this->dispatchCortexEvent($event);
            }
        };

        $request = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatRequest::class);
        $event = new BeforeChatSend($request);
        $dispatcher->dispatch($event);

        Event::assertNotDispatched(BeforeChatSend::class);
    });
});

describe('Provider Events', function () {
    it('creates ProviderRegistered event', function () {
        $provider = Mockery::mock(\JayI\Cortex\Plugins\Provider\Contracts\ProviderContract::class);
        $event = new ProviderRegistered($provider, 'test-provider', ['streaming' => true]);

        expect($event->provider)->toBe($provider);
        expect($event->providerId)->toBe('test-provider');
        expect($event->capabilities)->toBe(['streaming' => true]);
    });

    it('creates BeforeProviderRequest event', function () {
        $request = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatRequest::class);
        $event = new BeforeProviderRequest('bedrock', $request, 'claude-3');

        expect($event->provider)->toBe('bedrock');
        expect($event->request)->toBe($request);
        expect($event->model)->toBe('claude-3');
    });

    it('creates AfterProviderResponse event', function () {
        $request = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatRequest::class);
        $response = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatResponse::class);
        $event = new AfterProviderResponse('bedrock', $request, $response, 1.5);

        expect($event->provider)->toBe('bedrock');
        expect($event->duration)->toBe(1.5);
    });

    it('creates ProviderError event', function () {
        $request = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatRequest::class);
        $exception = new RuntimeException('Test error');
        $event = new ProviderError('bedrock', $request, $exception);

        expect($event->exception)->toBe($exception);
    });

    it('creates ProviderRateLimited event', function () {
        $event = new ProviderRateLimited('bedrock', 60);

        expect($event->provider)->toBe('bedrock');
        expect($event->retryAfter)->toBe(60);
    });
});

describe('Chat Events', function () {
    it('creates BeforeChatSend event', function () {
        $request = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatRequest::class);
        $event = new BeforeChatSend($request);

        expect($event->request)->toBe($request);
    });

    it('creates AfterChatReceive event', function () {
        $request = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatRequest::class);
        $response = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatResponse::class);
        $event = new AfterChatReceive($request, $response);

        expect($event->request)->toBe($request);
        expect($event->response)->toBe($response);
    });

    it('creates ChatError event', function () {
        $request = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatRequest::class);
        $exception = new RuntimeException('Test error');
        $event = new ChatError($request, $exception);

        expect($event->exception)->toBe($exception);
    });

    it('creates ChatStreamStarted event', function () {
        $request = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatRequest::class);
        $event = new ChatStreamStarted($request);

        expect($event->request)->toBe($request);
    });
});

describe('Tool Events', function () {
    it('creates ToolRegistered event', function () {
        $tool = Mockery::mock(\JayI\Cortex\Plugins\Tool\Contracts\ToolContract::class);
        $event = new ToolRegistered($tool);

        expect($event->tool)->toBe($tool);
    });

    it('creates BeforeToolExecution event', function () {
        $tool = Mockery::mock(\JayI\Cortex\Plugins\Tool\Contracts\ToolContract::class);
        $event = new BeforeToolExecution($tool, ['query' => 'test'], ['key' => 'value']);

        expect($event->tool)->toBe($tool);
        expect($event->input)->toBe(['query' => 'test']);
        expect($event->context)->toBe(['key' => 'value']);
    });

    it('creates AfterToolExecution event', function () {
        $tool = Mockery::mock(\JayI\Cortex\Plugins\Tool\Contracts\ToolContract::class);
        $result = Mockery::mock(\JayI\Cortex\Plugins\Tool\ToolResult::class);
        $event = new AfterToolExecution($tool, ['query' => 'test'], $result, 0.5);

        expect($event->output)->toBe($result);
        expect($event->duration)->toBe(0.5);
    });

    it('creates ToolError event', function () {
        $tool = Mockery::mock(\JayI\Cortex\Plugins\Tool\Contracts\ToolContract::class);
        $exception = new RuntimeException('Test error');
        $event = new ToolError($tool, ['query' => 'test'], $exception);

        expect($event->exception)->toBe($exception);
    });
});

describe('Agent Events', function () {
    it('creates AgentRunStarted event', function () {
        $agent = Mockery::mock(\JayI\Cortex\Plugins\Agent\Contracts\AgentContract::class);
        $event = new AgentRunStarted($agent, 'test input');

        expect($event->agent)->toBe($agent);
        expect($event->input)->toBe('test input');
    });

    it('creates AgentIterationStarted event', function () {
        $agent = Mockery::mock(\JayI\Cortex\Plugins\Agent\Contracts\AgentContract::class);
        $event = new AgentIterationStarted($agent, 0, ['messages_count' => 5]);

        expect($event->iteration)->toBe(0);
        expect($event->state)->toBe(['messages_count' => 5]);
    });

    it('creates AgentIterationCompleted event', function () {
        $agent = Mockery::mock(\JayI\Cortex\Plugins\Agent\Contracts\AgentContract::class);
        $response = Mockery::mock(\JayI\Cortex\Plugins\Chat\ChatResponse::class);
        $event = new AgentIterationCompleted($agent, 0, ['messages_count' => 6], $response);

        expect($event->response)->toBe($response);
    });

    it('creates AgentToolCalled event', function () {
        $agent = Mockery::mock(\JayI\Cortex\Plugins\Agent\Contracts\AgentContract::class);
        $tool = Mockery::mock(\JayI\Cortex\Plugins\Tool\Contracts\ToolContract::class);
        $event = new AgentToolCalled($agent, $tool, ['query' => 'test']);

        expect($event->tool)->toBe($tool);
        expect($event->input)->toBe(['query' => 'test']);
    });

    it('creates AgentRunCompleted event', function () {
        $agent = Mockery::mock(\JayI\Cortex\Plugins\Agent\Contracts\AgentContract::class);
        $event = new AgentRunCompleted($agent, 'test input', 'test output', 3);

        expect($event->output)->toBe('test output');
        expect($event->iterations)->toBe(3);
    });

    it('creates AgentRunFailed event', function () {
        $agent = Mockery::mock(\JayI\Cortex\Plugins\Agent\Contracts\AgentContract::class);
        $exception = new RuntimeException('Test error');
        $event = new AgentRunFailed($agent, 'test input', $exception, 2);

        expect($event->exception)->toBe($exception);
        expect($event->iterations)->toBe(2);
    });

    it('creates AgentMaxIterationsReached event', function () {
        $agent = Mockery::mock(\JayI\Cortex\Plugins\Agent\Contracts\AgentContract::class);
        $event = new AgentMaxIterationsReached($agent, ['messages_count' => 10], 10);

        expect($event->maxIterations)->toBe(10);
    });
});

describe('Workflow Events', function () {
    it('creates WorkflowStarted event', function () {
        $workflow = Mockery::mock(\JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract::class);
        $event = new WorkflowStarted($workflow, ['key' => 'value'], 'run-123');

        expect($event->workflow)->toBe($workflow);
        expect($event->input)->toBe(['key' => 'value']);
        expect($event->runId)->toBe('run-123');
    });

    it('creates WorkflowCompleted event', function () {
        $workflow = Mockery::mock(\JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract::class);
        $event = new WorkflowCompleted($workflow, ['input' => 'value'], ['output' => 'result'], 'run-123');

        expect($event->output)->toBe(['output' => 'result']);
    });

    it('creates WorkflowFailed event', function () {
        $workflow = Mockery::mock(\JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract::class);
        $state = Mockery::mock(\JayI\Cortex\Plugins\Workflow\WorkflowState::class);
        $exception = new RuntimeException('Test error');
        $event = new WorkflowFailed($workflow, $state, $exception);

        expect($event->exception)->toBe($exception);
    });

    it('creates WorkflowPaused event', function () {
        $workflow = Mockery::mock(\JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract::class);
        $state = Mockery::mock(\JayI\Cortex\Plugins\Workflow\WorkflowState::class);
        $event = new WorkflowPaused($workflow, $state, 'Waiting for approval');

        expect($event->reason)->toBe('Waiting for approval');
    });

    it('creates WorkflowResumed event', function () {
        $workflow = Mockery::mock(\JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract::class);
        $state = Mockery::mock(\JayI\Cortex\Plugins\Workflow\WorkflowState::class);
        $event = new WorkflowResumed($workflow, $state, ['approved' => true]);

        expect($event->input)->toBe(['approved' => true]);
    });

    it('creates WorkflowNodeEntered event', function () {
        $workflow = Mockery::mock(\JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract::class);
        $state = Mockery::mock(\JayI\Cortex\Plugins\Workflow\WorkflowState::class);
        $event = new WorkflowNodeEntered($workflow, 'process-node', $state);

        expect($event->node)->toBe('process-node');
    });

    it('creates WorkflowNodeExited event', function () {
        $workflow = Mockery::mock(\JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract::class);
        $state = Mockery::mock(\JayI\Cortex\Plugins\Workflow\WorkflowState::class);
        $event = new WorkflowNodeExited($workflow, 'process-node', $state, ['result' => 'done']);

        expect($event->output)->toBe(['result' => 'done']);
    });
});

describe('Guardrail Events', function () {
    it('creates GuardrailChecked event', function () {
        $guardrail = Mockery::mock(\JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailContract::class);
        $result = Mockery::mock(\JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult::class);
        $event = new GuardrailChecked($guardrail, 'test content', $result);

        expect($event->guardrail)->toBe($guardrail);
        expect($event->content)->toBe('test content');
        expect($event->result)->toBe($result);
    });

    it('creates GuardrailBlocked event', function () {
        $guardrail = Mockery::mock(\JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailContract::class);
        $event = new GuardrailBlocked($guardrail, 'blocked content', ['banned keyword']);

        expect($event->content)->toBe('blocked content');
        expect($event->violations)->toBe(['banned keyword']);
    });
});
