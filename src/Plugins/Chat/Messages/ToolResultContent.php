<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Messages;

class ToolResultContent extends Content
{
    public function __construct(
        public readonly string $toolUseId,
        public readonly mixed $result,
        public readonly bool $isError = false,
    ) {}

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'tool_result',
            'tool_use_id' => $this->toolUseId,
            'content' => $this->formatResult(),
            'is_error' => $this->isError,
        ];
    }

    /**
     * Get the content type.
     */
    public function type(): string
    {
        return 'tool_result';
    }

    /**
     * Format the result for output.
     */
    protected function formatResult(): string
    {
        if (is_string($this->result)) {
            return $this->result;
        }

        if (is_array($this->result) || is_object($this->result)) {
            return json_encode($this->result, JSON_PRETTY_PRINT) ?: '';
        }

        return (string) $this->result;
    }
}
