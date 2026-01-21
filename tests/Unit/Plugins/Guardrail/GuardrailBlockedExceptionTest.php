<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;
use JayI\Cortex\Plugins\Guardrail\Exceptions\GuardrailBlockedException;

describe('GuardrailBlockedException', function () {
    it('creates with message and result', function () {
        $result = new GuardrailResult(
            passed: false,
            guardrailId: 'pii-filter',
            reason: 'PII detected',
        );

        $exception = new GuardrailBlockedException('Content blocked', $result);

        expect($exception->getMessage())->toBe('Content blocked');
        expect($exception->result)->toBe($result);
    });

    it('creates from result with reason', function () {
        $result = new GuardrailResult(
            passed: false,
            guardrailId: 'keyword-filter',
            reason: 'Prohibited keywords found',
        );

        $exception = GuardrailBlockedException::fromResult($result);

        expect($exception->getMessage())->toBe('Prohibited keywords found');
        expect($exception->result)->toBe($result);
    });

    it('creates from result without reason', function () {
        $result = new GuardrailResult(
            passed: false,
            guardrailId: 'custom-filter',
            reason: null,
        );

        $exception = GuardrailBlockedException::fromResult($result);

        expect($exception->getMessage())->toContain('custom-filter');
        expect($exception->getMessage())->toContain('blocked');
        expect($exception->result)->toBe($result);
    });

    it('exposes guardrail result', function () {
        $result = new GuardrailResult(
            passed: false,
            guardrailId: 'injection-filter',
            reason: 'Prompt injection detected',
            metadata: ['severity' => 'high'],
        );

        $exception = GuardrailBlockedException::fromResult($result);

        expect($exception->result->guardrailId)->toBe('injection-filter');
        expect($exception->result->metadata['severity'])->toBe('high');
    });
});
