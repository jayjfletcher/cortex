<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\StreamChunk;
use JayI\Cortex\Plugins\Chat\StreamedResponse;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;

describe('StreamedResponse', function () {
    it('iterates over stream chunks', function () {
        $chunks = [
            StreamChunk::textDelta('Hello', 0),
            StreamChunk::textDelta(' World', 1),
            StreamChunk::messageComplete(Usage::zero(), StopReason::EndTurn, 2),
        ];

        $stream = new StreamedResponse(function () use ($chunks) {
            foreach ($chunks as $index => $chunk) {
                yield $index => $chunk;
            }
        });

        $collected = [];
        foreach ($stream as $chunk) {
            $collected[] = $chunk;
        }

        expect($collected)->toHaveCount(3);
        expect($collected[0]->text)->toBe('Hello');
        expect($collected[1]->text)->toBe(' World');
    });

    it('collects chunks into final response', function () {
        $chunks = [
            StreamChunk::textDelta('Hello', 0),
            StreamChunk::textDelta(' World', 1),
            StreamChunk::messageComplete(
                new Usage(10, 2, 12),
                StopReason::EndTurn,
                2
            ),
        ];

        $stream = new StreamedResponse(function () use ($chunks) {
            foreach ($chunks as $index => $chunk) {
                yield $index => $chunk;
            }
        });

        $response = $stream->collect();

        expect($response)->toBeInstanceOf(ChatResponse::class);
        expect($response->content())->toBe('Hello World');
        expect($response->usage->totalTokens())->toBe(12);
    });

    it('processes chunks with callback', function () {
        $chunks = [
            StreamChunk::textDelta('Hello', 0),
            StreamChunk::textDelta(' World', 1),
            StreamChunk::messageComplete(Usage::zero(), StopReason::EndTurn, 2),
        ];

        $stream = new StreamedResponse(function () use ($chunks) {
            foreach ($chunks as $index => $chunk) {
                yield $index => $chunk;
            }
        });

        $texts = [];
        $response = $stream->each(function (StreamChunk $chunk) use (&$texts) {
            if ($chunk->text !== null) {
                $texts[] = $chunk->text;
            }
        });

        expect($texts)->toBe(['Hello', ' World']);
        expect($response)->toBeInstanceOf(ChatResponse::class);
    });

    it('streams text content', function () {
        $chunks = [
            StreamChunk::textDelta('Hello', 0),
            StreamChunk::textDelta(' World', 1),
            StreamChunk::messageComplete(Usage::zero(), StopReason::EndTurn, 2),
        ];

        $stream = new StreamedResponse(function () use ($chunks) {
            foreach ($chunks as $index => $chunk) {
                yield $index => $chunk;
            }
        });

        $texts = [];
        foreach ($stream->text() as $text) {
            $texts[] = $text;
        }

        expect($texts)->toBe(['Hello', ' World']);
    });

    it('handles tool calls in stream', function () {
        $toolUse = new ToolUseContent('toolu_123', 'search', ['query' => 'test']);
        $chunks = [
            StreamChunk::textDelta('Let me search', 0),
            StreamChunk::toolUseStart($toolUse, 1),
            StreamChunk::messageComplete(Usage::zero(), StopReason::ToolUse, 2),
        ];

        $stream = new StreamedResponse(function () use ($chunks) {
            foreach ($chunks as $index => $chunk) {
                yield $index => $chunk;
            }
        });

        $response = $stream->collect();

        expect($response->stopReason)->toBe(StopReason::ToolUse);
        expect($response->toolCalls())->toHaveCount(1);
    });

    it('accepts generator directly', function () {
        $generator = (function () {
            yield 0 => StreamChunk::textDelta('Hello', 0);
            yield 1 => StreamChunk::messageComplete(Usage::zero(), StopReason::EndTurn, 1);
        })();

        $stream = new StreamedResponse($generator);
        $response = $stream->collect();

        expect($response->content())->toBe('Hello');
    });
});
