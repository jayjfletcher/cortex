<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Data;

/**
 * Type of content being evaluated.
 */
enum ContentType: string
{
    case Input = 'input';
    case Output = 'output';
    case System = 'system';
}
