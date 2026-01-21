<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailPipelineContract;
use JayI\Cortex\Plugins\Guardrail\Guardrails\ContentLengthGuardrail;
use JayI\Cortex\Plugins\Guardrail\Guardrails\KeywordGuardrail;
use JayI\Cortex\Plugins\Guardrail\Guardrails\PiiGuardrail;
use JayI\Cortex\Plugins\Guardrail\Guardrails\PromptInjectionGuardrail;

class GuardrailPlugin implements PluginContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Container $container,
        protected array $config = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'guardrail';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Guardrail';
    }

    /**
     * {@inheritdoc}
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function provides(): array
    {
        return ['guardrail'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        $this->container->singleton(GuardrailPipelineContract::class, function () {
            return $this->createPipeline();
        });

        $this->container->bind(GuardrailPipeline::class, function () {
            return $this->container->make(GuardrailPipelineContract::class);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(PluginManagerContract $manager): void
    {
        // No boot actions needed
    }

    /**
     * Create and configure the guardrail pipeline from config.
     */
    protected function createPipeline(): GuardrailPipeline
    {
        $pipeline = GuardrailPipeline::make();

        // Prompt injection detection (high priority)
        if ($this->config['prompt_injection']['enabled'] ?? true) {
            $guardrail = new PromptInjectionGuardrail;

            if (isset($this->config['prompt_injection']['threshold'])) {
                $guardrail->setThreshold($this->config['prompt_injection']['threshold']);
            }

            $pipeline->add($guardrail);
        }

        // PII detection
        if ($this->config['pii']['enabled'] ?? false) {
            $guardrail = new PiiGuardrail(
                enabledTypes: $this->config['pii']['types'] ?? ['email', 'phone_us', 'ssn', 'credit_card'],
                blockOnDetection: $this->config['pii']['block'] ?? true,
            );
            $pipeline->add($guardrail);
        }

        // Keyword filtering
        if ($this->config['keyword']['enabled'] ?? false) {
            $guardrail = new KeywordGuardrail(
                blockedKeywords: $this->config['keyword']['keywords'] ?? [],
                blockedPatterns: $this->config['keyword']['patterns'] ?? [],
                caseSensitive: $this->config['keyword']['case_sensitive'] ?? false,
            );
            $pipeline->add($guardrail);
        }

        // Content length limits
        if ($this->config['content_length']['enabled'] ?? false) {
            $guardrail = new ContentLengthGuardrail(
                minLength: $this->config['content_length']['min'] ?? null,
                maxLength: $this->config['content_length']['max'] ?? null,
                countTokens: $this->config['content_length']['count_tokens'] ?? false,
            );
            $pipeline->add($guardrail);
        }

        return $pipeline;
    }
}
