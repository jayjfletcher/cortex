<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Guardrail\Guardrails;

use JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailContract;
use JayI\Cortex\Plugins\Guardrail\Data\ContentType;

/**
 * Base class for guardrails.
 */
abstract class AbstractGuardrail implements GuardrailContract
{
    protected bool $enabled = true;

    protected int $priority = 0;

    /** @var array<int, ContentType> */
    protected array $contentTypes = [ContentType::Input, ContentType::Output];

    /**
     * {@inheritdoc}
     */
    public function appliesTo(): array
    {
        return $this->contentTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function priority(): int
    {
        return $this->priority;
    }

    /**
     * Set whether the guardrail is enabled.
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Set the priority.
     */
    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Set the content types this guardrail applies to.
     *
     * @param  array<int, ContentType>  $contentTypes
     */
    public function setContentTypes(array $contentTypes): self
    {
        $this->contentTypes = $contentTypes;

        return $this;
    }
}
