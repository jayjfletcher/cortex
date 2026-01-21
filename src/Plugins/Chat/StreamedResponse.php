<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use Closure;
use Generator;
use IteratorAggregate;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\TextContent;
use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;
use Traversable;

/**
 * @implements IteratorAggregate<int, StreamChunk>
 */
class StreamedResponse implements IteratorAggregate
{
    /**
     * @var Generator<int, StreamChunk>|null
     */
    protected ?Generator $stream = null;

    /**
     * @var array<int, StreamChunk>
     */
    protected array $collectedChunks = [];

    protected bool $collected = false;

    /**
     * @param  Generator<int, StreamChunk>|Closure(): Generator<int, StreamChunk>  $streamGenerator
     */
    public function __construct(
        protected Generator|Closure $streamGenerator,
    ) {}

    /**
     * Iterate over stream chunks.
     *
     * @return Traversable<int, StreamChunk>
     */
    public function getIterator(): Traversable
    {
        $generator = $this->streamGenerator instanceof Closure
            ? ($this->streamGenerator)()
            : $this->streamGenerator;

        foreach ($generator as $index => $chunk) {
            $this->collectedChunks[] = $chunk;
            yield $index => $chunk;
        }

        $this->collected = true;
    }

    /**
     * Collect all chunks into a final response.
     */
    public function collect(): ChatResponse
    {
        if (! $this->collected) {
            foreach ($this as $chunk) {
                // Iterate to collect
            }
        }

        return $this->buildResponse();
    }

    /**
     * Process chunks with a callback.
     */
    public function each(Closure $callback): ChatResponse
    {
        foreach ($this as $index => $chunk) {
            $callback($chunk, $index);
        }

        return $this->buildResponse();
    }

    /**
     * Get text content as it streams.
     *
     * @return Generator<int, string>
     */
    public function text(): Generator
    {
        foreach ($this as $chunk) {
            if ($chunk->isText() && $chunk->text !== null) {
                yield $chunk->text;
            }
        }
    }

    /**
     * Build the final response from collected chunks.
     */
    protected function buildResponse(): ChatResponse
    {
        $textParts = [];
        $toolCalls = [];
        $usage = Usage::zero();
        $stopReason = StopReason::EndTurn;

        foreach ($this->collectedChunks as $chunk) {
            if ($chunk->isText() && $chunk->text !== null) {
                $textParts[] = $chunk->text;
            }

            if ($chunk->toolUse !== null) {
                $toolCalls[] = $chunk->toolUse;
            }

            if ($chunk->usage !== null) {
                $usage = $usage->add($chunk->usage);
            }

            if ($chunk->stopReason !== null) {
                $stopReason = $chunk->stopReason;
            }
        }

        $content = [];
        if (count($textParts) > 0) {
            $content[] = new TextContent(implode('', $textParts));
        }
        foreach ($toolCalls as $toolCall) {
            $content[] = $toolCall;
        }

        return new ChatResponse(
            message: new Message(
                role: MessageRole::Assistant,
                content: $content,
            ),
            usage: $usage,
            stopReason: $stopReason,
        );
    }
}
