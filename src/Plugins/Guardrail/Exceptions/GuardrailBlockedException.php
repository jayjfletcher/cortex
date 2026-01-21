<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Exceptions;

use Exception;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;

/**
 * Exception thrown when content is blocked by a guardrail.
 */
class GuardrailBlockedException extends Exception
{
    public function __construct(
        string $message,
        public readonly GuardrailResult $result,
    ) {
        parent::__construct($message);
    }

    /**
     * Create from a guardrail result.
     */
    public static function fromResult(GuardrailResult $result): self
    {
        $message = $result->reason ?? "Content blocked by guardrail: {$result->guardrailId}";

        return new self($message, $result);
    }
}
