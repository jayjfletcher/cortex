<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Usage\Estimators\AnthropicCostEstimator;

describe('AnthropicCostEstimator', function () {
    test('estimates cost for Claude Sonnet', function () {
        $estimator = new AnthropicCostEstimator;

        // 1000 input tokens at $3/million + 500 output tokens at $15/million
        $cost = $estimator->estimate('claude-3-5-sonnet', 1000, 500);

        // (1000/1000000 * 3) + (500/1000000 * 15) = 0.003 + 0.0075 = 0.0105
        expect($cost)->toBeGreaterThan(0);
        expect($cost)->toBeLessThan(0.02);
    });

    test('estimates cost for Claude Opus', function () {
        $estimator = new AnthropicCostEstimator;

        // 1000 input at $15/million + 500 output at $75/million
        $cost = $estimator->estimate('claude-3-opus', 1000, 500);

        // More expensive than sonnet
        expect($cost)->toBeGreaterThan(0.01);
        expect($cost)->toBeLessThan(0.1);
    });

    test('estimates cost for Claude Haiku', function () {
        $estimator = new AnthropicCostEstimator;

        // 1000 input at $0.25/million + 500 output at $1.25/million
        $cost = $estimator->estimate('claude-3-haiku', 1000, 500);

        // Cheapest model
        expect($cost)->toBeGreaterThan(0);
        expect($cost)->toBeLessThan(0.01);
    });

    test('supports model detection', function () {
        $estimator = new AnthropicCostEstimator;

        expect($estimator->supports('claude-3-sonnet'))->toBeTrue();
        expect($estimator->supports('claude-3-5-sonnet'))->toBeTrue();
        expect($estimator->supports('claude-3-opus'))->toBeTrue();
        expect($estimator->supports('claude-3-haiku'))->toBeTrue();
        expect($estimator->supports('gpt-4'))->toBeFalse();
    });

    test('matches Bedrock model IDs', function () {
        $estimator = new AnthropicCostEstimator;

        expect($estimator->supports('anthropic.claude-3-5-sonnet-20241022-v2:0'))->toBeTrue();
        expect($estimator->supports('anthropic.claude-3-opus-20240229-v1:0'))->toBeTrue();
    });

    test('allows custom pricing', function () {
        $estimator = new AnthropicCostEstimator;
        $estimator->setPricing('custom-model', 10.0, 30.0);

        $cost = $estimator->estimate('custom-model', 1000000, 1000000);

        // 1M input at $10/million + 1M output at $30/million = 10 + 30 = 40
        expect($cost)->toBe(40.0);
    });

    test('gets pricing per million', function () {
        $estimator = new AnthropicCostEstimator;

        expect($estimator->getInputPricePerMillion('claude-3-5-sonnet'))->toBe(3.0);
        expect($estimator->getOutputPricePerMillion('claude-3-5-sonnet'))->toBe(15.0);
    });

    test('matches claude-instant model', function () {
        $estimator = new AnthropicCostEstimator;

        expect($estimator->supports('claude-instant'))->toBeTrue();
        expect($estimator->getInputPricePerMillion('claude-instant'))->toBe(0.80);
    });

    test('matches claude-2 model', function () {
        $estimator = new AnthropicCostEstimator;

        expect($estimator->supports('claude-2'))->toBeTrue();
        expect($estimator->getInputPricePerMillion('claude-2'))->toBe(8.0);
    });

    test('matches claude-3.5 variations', function () {
        $estimator = new AnthropicCostEstimator;

        // 3.5 with dot
        expect($estimator->supports('some-claude-3.5-sonnet-model'))->toBeTrue();

        // 3-5 with dash
        expect($estimator->supports('anthropic.claude-3-5-sonnet'))->toBeTrue();
    });

    test('matches claude-3.5-haiku', function () {
        $estimator = new AnthropicCostEstimator;

        expect($estimator->supports('claude-3-5-haiku'))->toBeTrue();
        expect($estimator->supports('claude-3.5-haiku'))->toBeTrue();
        expect($estimator->getInputPricePerMillion('claude-3-5-haiku'))->toBe(1.0);
    });

    test('defaults to sonnet pricing for unknown model', function () {
        $estimator = new AnthropicCostEstimator;

        // Unknown model defaults to sonnet pricing
        $inputPrice = $estimator->getInputPricePerMillion('unknown-model');
        expect($inputPrice)->toBe(3.0); // Sonnet default
    });

    test('estimates cost for large token counts', function () {
        $estimator = new AnthropicCostEstimator;

        $cost = $estimator->estimate('claude-3-5-sonnet', 1000000, 500000);

        // 1M input at $3 + 500K output at $15 = $3 + $7.5 = $10.5
        expect($cost)->toBe(10.5);
    });
});
