<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail;

use JayI\Cortex\Events\Concerns\DispatchesCortexEvents;
use JayI\Cortex\Events\Guardrail\GuardrailBlocked;
use JayI\Cortex\Events\Guardrail\GuardrailChecked;
use JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailContract;
use JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailPipelineContract;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailContext;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;

/**
 * Pipeline for running multiple guardrails.
 */
class GuardrailPipeline implements GuardrailPipelineContract
{
    use DispatchesCortexEvents;

    /** @var array<string, GuardrailContract> */
    protected array $guardrails = [];

    /**
     * Create a new pipeline.
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * {@inheritdoc}
     */
    public function add(GuardrailContract $guardrail): self
    {
        $this->guardrails[$guardrail->id()] = $guardrail;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $guardrailId): self
    {
        unset($this->guardrails[$guardrailId]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(GuardrailContext $context): array
    {
        $results = [];
        $applicableGuardrails = $this->getApplicableGuardrails($context);

        // Sort by priority (higher first)
        usort($applicableGuardrails, fn (GuardrailContract $a, GuardrailContract $b) => $b->priority() <=> $a->priority());

        foreach ($applicableGuardrails as $guardrail) {
            $result = $guardrail->evaluate($context);
            $results[] = $result;

            $this->dispatchCortexEvent(new GuardrailChecked(
                guardrail: $guardrail,
                content: $context->content,
                result: $result,
            ));

            if (! $result->passed) {
                $this->dispatchCortexEvent(new GuardrailBlocked(
                    guardrail: $guardrail,
                    content: $context->content,
                    violations: $result->reason ? [$result->reason] : [],
                ));
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function passes(GuardrailContext $context): bool
    {
        $results = $this->evaluate($context);

        foreach ($results as $result) {
            if (! $result->passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function firstFailure(GuardrailContext $context): ?GuardrailResult
    {
        $results = $this->evaluate($context);

        foreach ($results as $result) {
            if (! $result->passed) {
                return $result;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->guardrails;
    }

    /**
     * Get guardrails applicable to the context.
     *
     * @return array<int, GuardrailContract>
     */
    protected function getApplicableGuardrails(GuardrailContext $context): array
    {
        return array_values(array_filter(
            $this->guardrails,
            function (GuardrailContract $guardrail) use ($context) {
                if (! $guardrail->isEnabled()) {
                    return false;
                }

                $appliesTo = $guardrail->appliesTo();

                return in_array($context->contentType, $appliesTo, true);
            }
        ));
    }

    /**
     * Get a specific guardrail by ID.
     */
    public function get(string $guardrailId): ?GuardrailContract
    {
        return $this->guardrails[$guardrailId] ?? null;
    }

    /**
     * Check if a guardrail exists.
     */
    public function has(string $guardrailId): bool
    {
        return isset($this->guardrails[$guardrailId]);
    }
}
