<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\StructuredOutput\Contracts;

use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\StructuredOutput\StructuredResponse;

interface StructuredOutputContract
{
    /**
     * Request structured output matching a schema.
     */
    public function generate(ChatRequest $request, Schema $schema): StructuredResponse;

    /**
     * Request structured output matching a Data class.
     *
     * @template T of object
     *
     * @param  class-string<T>  $dataClass
     * @return T
     */
    public function generateAs(ChatRequest $request, string $dataClass): object;
}
