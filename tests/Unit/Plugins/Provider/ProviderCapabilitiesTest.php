<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Provider\Model;
use JayI\Cortex\Plugins\Provider\ProviderCapabilities;

describe('ProviderCapabilities', function () {
    it('creates with defaults', function () {
        $capabilities = new ProviderCapabilities();

        expect($capabilities->streaming)->toBeFalse();
        expect($capabilities->tools)->toBeFalse();
        expect($capabilities->parallelTools)->toBeFalse();
        expect($capabilities->vision)->toBeFalse();
        expect($capabilities->audio)->toBeFalse();
        expect($capabilities->documents)->toBeFalse();
        expect($capabilities->structuredOutput)->toBeFalse();
        expect($capabilities->jsonMode)->toBeFalse();
        expect($capabilities->promptCaching)->toBeFalse();
        expect($capabilities->systemMessages)->toBeTrue();
        expect($capabilities->maxContextWindow)->toBe(4096);
        expect($capabilities->maxOutputTokens)->toBe(4096);
    });

    it('creates with custom values', function () {
        $capabilities = new ProviderCapabilities(
            streaming: true,
            tools: true,
            vision: true,
            maxContextWindow: 128000,
            maxOutputTokens: 8192,
        );

        expect($capabilities->streaming)->toBeTrue();
        expect($capabilities->tools)->toBeTrue();
        expect($capabilities->vision)->toBeTrue();
        expect($capabilities->maxContextWindow)->toBe(128000);
        expect($capabilities->maxOutputTokens)->toBe(8192);
    });

    it('checks streaming support', function () {
        $capabilities = new ProviderCapabilities(streaming: true);
        expect($capabilities->supports('streaming'))->toBeTrue();

        $capabilities = new ProviderCapabilities(streaming: false);
        expect($capabilities->supports('streaming'))->toBeFalse();
    });

    it('checks tools support', function () {
        $capabilities = new ProviderCapabilities(tools: true);
        expect($capabilities->supports('tools'))->toBeTrue();
        expect($capabilities->supports('tool_use'))->toBeTrue();

        $capabilities = new ProviderCapabilities(tools: false);
        expect($capabilities->supports('tools'))->toBeFalse();
    });

    it('checks parallel tools support', function () {
        $capabilities = new ProviderCapabilities(parallelTools: true);
        expect($capabilities->supports('parallel_tools'))->toBeTrue();
    });

    it('checks vision support', function () {
        $capabilities = new ProviderCapabilities(vision: true);
        expect($capabilities->supports('vision'))->toBeTrue();
    });

    it('checks audio support', function () {
        $capabilities = new ProviderCapabilities(audio: true);
        expect($capabilities->supports('audio'))->toBeTrue();
    });

    it('checks documents support', function () {
        $capabilities = new ProviderCapabilities(documents: true);
        expect($capabilities->supports('documents'))->toBeTrue();
    });

    it('checks structured output support', function () {
        $capabilities = new ProviderCapabilities(structuredOutput: true);
        expect($capabilities->supports('structured_output'))->toBeTrue();
    });

    it('checks json mode support', function () {
        $capabilities = new ProviderCapabilities(jsonMode: true);
        expect($capabilities->supports('json_mode'))->toBeTrue();
    });

    it('checks prompt caching support', function () {
        $capabilities = new ProviderCapabilities(promptCaching: true);
        expect($capabilities->supports('prompt_caching'))->toBeTrue();
    });

    it('checks system messages support', function () {
        $capabilities = new ProviderCapabilities(systemMessages: true);
        expect($capabilities->supports('system_messages'))->toBeTrue();

        $capabilities = new ProviderCapabilities(systemMessages: false);
        expect($capabilities->supports('system_messages'))->toBeFalse();
    });

    it('checks custom capabilities', function () {
        $capabilities = new ProviderCapabilities(
            custom: ['custom_feature' => true, 'disabled_feature' => false],
        );

        expect($capabilities->supports('custom_feature'))->toBeTrue();
        expect($capabilities->supports('disabled_feature'))->toBeFalse();
    });

    it('returns false for unknown capabilities', function () {
        $capabilities = new ProviderCapabilities();
        expect($capabilities->supports('unknown_feature'))->toBeFalse();
    });

    it('stores supported media types', function () {
        $mediaTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $capabilities = new ProviderCapabilities(supportedMediaTypes: $mediaTypes);

        expect($capabilities->supportedMediaTypes)->toBe($mediaTypes);
    });
});

describe('Model', function () {
    it('creates with required parameters', function () {
        $model = new Model(
            id: 'claude-3-opus',
            name: 'Claude 3 Opus',
            provider: 'anthropic',
            contextWindow: 200000,
            maxOutputTokens: 4096,
        );

        expect($model->id)->toBe('claude-3-opus');
        expect($model->name)->toBe('Claude 3 Opus');
        expect($model->provider)->toBe('anthropic');
        expect($model->contextWindow)->toBe(200000);
        expect($model->maxOutputTokens)->toBe(4096);
    });

    it('estimates cost correctly', function () {
        $model = new Model(
            id: 'test-model',
            name: 'Test',
            provider: 'test',
            contextWindow: 8192,
            maxOutputTokens: 4096,
            inputCostPer1kTokens: 0.01,
            outputCostPer1kTokens: 0.03,
        );

        // 1000 input tokens * 0.01/1k + 500 output tokens * 0.03/1k
        $cost = $model->estimateCost(1000, 500);
        expect($cost)->toBe(0.025);
    });

    it('returns null for cost when pricing not set', function () {
        $model = new Model(
            id: 'test-model',
            name: 'Test',
            provider: 'test',
            contextWindow: 8192,
            maxOutputTokens: 4096,
        );

        $cost = $model->estimateCost(1000, 500);
        expect($cost)->toBeNull();
    });

    it('returns null for cost when only input pricing set', function () {
        $model = new Model(
            id: 'test-model',
            name: 'Test',
            provider: 'test',
            contextWindow: 8192,
            maxOutputTokens: 4096,
            inputCostPer1kTokens: 0.01,
        );

        $cost = $model->estimateCost(1000, 500);
        expect($cost)->toBeNull();
    });

    it('checks capabilities support', function () {
        $capabilities = new ProviderCapabilities(
            streaming: true,
            tools: true,
        );

        $model = new Model(
            id: 'test-model',
            name: 'Test',
            provider: 'test',
            contextWindow: 8192,
            maxOutputTokens: 4096,
            capabilities: $capabilities,
        );

        expect($model->supports('streaming'))->toBeTrue();
        expect($model->supports('tools'))->toBeTrue();
        expect($model->supports('vision'))->toBeFalse();
    });

    it('returns false for support when no capabilities', function () {
        $model = new Model(
            id: 'test-model',
            name: 'Test',
            provider: 'test',
            contextWindow: 8192,
            maxOutputTokens: 4096,
        );

        expect($model->supports('streaming'))->toBeFalse();
        expect($model->supports('tools'))->toBeFalse();
    });

    it('stores metadata', function () {
        $model = new Model(
            id: 'test-model',
            name: 'Test',
            provider: 'test',
            contextWindow: 8192,
            maxOutputTokens: 4096,
            metadata: ['family' => 'claude-3', 'version' => '20240229'],
        );

        expect($model->metadata)->toBe(['family' => 'claude-3', 'version' => '20240229']);
    });
});
