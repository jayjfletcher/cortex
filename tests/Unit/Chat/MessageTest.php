<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Chat\Messages\ImageContent;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\Messages\TextContent;
use JayI\Cortex\Plugins\Chat\Messages\ToolResultContent;
use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;

describe('Message', function () {
    it('creates a system message', function () {
        $message = Message::system('You are a helpful assistant.');

        expect($message->role)->toBe(MessageRole::System);
        expect($message->text())->toBe('You are a helpful assistant.');
    });

    it('creates a user message from string', function () {
        $message = Message::user('Hello!');

        expect($message->role)->toBe(MessageRole::User);
        expect($message->text())->toBe('Hello!');
    });

    it('creates an assistant message', function () {
        $message = Message::assistant('Hi there! How can I help?');

        expect($message->role)->toBe(MessageRole::Assistant);
        expect($message->text())->toBe('Hi there! How can I help?');
    });

    it('creates a user message with multiple content blocks', function () {
        $message = Message::user([
            new TextContent('Check out this image:'),
            new TextContent('What do you see?'),
        ]);

        expect($message->content)->toHaveCount(2);
        expect($message->text())->toBe("Check out this image:\nWhat do you see?");
    });

    it('creates a tool result message', function () {
        $message = Message::toolResult('tool_123', ['temperature' => 72]);

        expect($message->role)->toBe(MessageRole::User);
        expect($message->content[0])->toBeInstanceOf(ToolResultContent::class);
    });

    it('extracts tool calls from message', function () {
        $message = new Message(
            role: MessageRole::Assistant,
            content: [
                new TextContent('Let me check that.'),
                new ToolUseContent('tool_1', 'get_weather', ['location' => 'NYC']),
                new ToolUseContent('tool_2', 'get_time', ['timezone' => 'EST']),
            ],
        );

        $toolCalls = $message->toolCalls();

        expect($toolCalls)->toHaveCount(2);
        expect($toolCalls[0]->name)->toBe('get_weather');
        expect($toolCalls[1]->name)->toBe('get_time');
    });

    it('detects when message has tool calls', function () {
        $messageWithTools = new Message(
            role: MessageRole::Assistant,
            content: [
                new ToolUseContent('tool_1', 'test', []),
            ],
        );

        $messageWithoutTools = Message::assistant('Just text');

        expect($messageWithTools->hasToolCalls())->toBeTrue();
        expect($messageWithoutTools->hasToolCalls())->toBeFalse();
    });

    it('converts to array', function () {
        $message = Message::user('Hello');

        $array = $message->toArray();

        expect($array['role'])->toBe('user');
        expect($array['content'][0]['type'])->toBe('text');
        expect($array['content'][0]['text'])->toBe('Hello');
    });
});

describe('MessageCollection', function () {
    it('creates an empty collection', function () {
        $collection = MessageCollection::make();

        expect($collection->isEmpty())->toBeTrue();
        expect($collection->count())->toBe(0);
    });

    it('adds messages fluently', function () {
        $collection = MessageCollection::make()
            ->system('System prompt')
            ->user('User message')
            ->assistant('Assistant response');

        expect($collection->count())->toBe(3);
    });

    it('gets first and last messages', function () {
        $collection = MessageCollection::make()
            ->user('First')
            ->assistant('Second')
            ->user('Third');

        expect($collection->first()?->text())->toBe('First');
        expect($collection->last()?->text())->toBe('Third');
    });

    it('filters messages by role', function () {
        $collection = MessageCollection::make()
            ->system('System')
            ->user('User 1')
            ->assistant('Assistant')
            ->user('User 2');

        $userMessages = $collection->byRole(MessageRole::User);

        expect($userMessages->count())->toBe(2);
    });

    it('excludes system messages', function () {
        $collection = MessageCollection::make()
            ->system('System')
            ->user('User')
            ->assistant('Assistant');

        $withoutSystem = $collection->withoutSystem();

        expect($withoutSystem->count())->toBe(2);
        expect($withoutSystem->first()?->role)->toBe(MessageRole::User);
    });

    it('is iterable', function () {
        $collection = MessageCollection::make()
            ->user('One')
            ->user('Two')
            ->user('Three');

        $texts = [];
        foreach ($collection as $message) {
            $texts[] = $message->text();
        }

        expect($texts)->toBe(['One', 'Two', 'Three']);
    });

    it('converts to array', function () {
        $collection = MessageCollection::make()
            ->user('Hello')
            ->assistant('Hi');

        $array = $collection->toArray();

        expect($array)->toHaveCount(2);
        expect($array[0]['role'])->toBe('user');
        expect($array[1]['role'])->toBe('assistant');
    });

    it('prepends messages', function () {
        $collection = MessageCollection::make()
            ->user('Second');

        $collection->prepend(Message::system('First'));

        expect($collection->first()?->role)->toBe(MessageRole::System);
    });

    it('pushes multiple messages', function () {
        $collection = MessageCollection::make();

        $collection->push(
            Message::user('One'),
            Message::assistant('Two'),
            Message::user('Three'),
        );

        expect($collection->count())->toBe(3);
    });
});

describe('DocumentContent', function () {
    it('creates from base64', function () {
        $content = \JayI\Cortex\Plugins\Chat\Messages\DocumentContent::fromBase64(
            'SGVsbG8gV29ybGQ=',
            'application/pdf',
            'test.pdf'
        );

        expect($content->source)->toBe('SGVsbG8gV29ybGQ=');
        expect($content->mediaType)->toBe('application/pdf');
        expect($content->name)->toBe('test.pdf');
    });

    it('converts to array without name', function () {
        $content = new \JayI\Cortex\Plugins\Chat\Messages\DocumentContent(
            'base64data',
            'application/pdf'
        );

        $array = $content->toArray();

        expect($array['type'])->toBe('document');
        expect($array['source'])->toBe('base64data');
        expect($array['media_type'])->toBe('application/pdf');
        expect($array)->not->toHaveKey('name');
    });

    it('converts to array with name', function () {
        $content = new \JayI\Cortex\Plugins\Chat\Messages\DocumentContent(
            'base64data',
            'application/pdf',
            'document.pdf'
        );

        $array = $content->toArray();

        expect($array['name'])->toBe('document.pdf');
    });

    it('returns correct type', function () {
        $content = new \JayI\Cortex\Plugins\Chat\Messages\DocumentContent('data', 'application/pdf');

        expect($content->type())->toBe('document');
    });
});

describe('ImageContent', function () {
    it('creates from base64', function () {
        $content = ImageContent::fromBase64('imagedata', 'image/png');

        expect($content->source)->toBe('imagedata');
        expect($content->mediaType)->toBe('image/png');
        expect($content->sourceType)->toBe(\JayI\Cortex\Plugins\Chat\Messages\SourceType::Base64);
    });

    it('creates from URL with jpeg extension', function () {
        $content = ImageContent::fromUrl('https://example.com/image.jpeg');

        expect($content->source)->toBe('https://example.com/image.jpeg');
        expect($content->mediaType)->toBe('image/jpeg');
        expect($content->sourceType)->toBe(\JayI\Cortex\Plugins\Chat\Messages\SourceType::Url);
    });

    it('creates from URL with png extension', function () {
        $content = ImageContent::fromUrl('https://example.com/image.png');

        expect($content->mediaType)->toBe('image/png');
    });

    it('creates from URL with gif extension', function () {
        $content = ImageContent::fromUrl('https://example.com/image.gif');

        expect($content->mediaType)->toBe('image/gif');
    });

    it('creates from URL with webp extension', function () {
        $content = ImageContent::fromUrl('https://example.com/image.webp');

        expect($content->mediaType)->toBe('image/webp');
    });

    it('creates from URL with unknown extension', function () {
        $content = ImageContent::fromUrl('https://example.com/image.unknown');

        expect($content->mediaType)->toBe('image/jpeg');
    });

    it('converts to array', function () {
        $content = ImageContent::fromBase64('data', 'image/png');

        $array = $content->toArray();

        expect($array['type'])->toBe('image');
        expect($array['source'])->toBe('data');
        expect($array['media_type'])->toBe('image/png');
        expect($array['source_type'])->toBe('base64');
    });

    it('returns correct type', function () {
        $content = ImageContent::fromBase64('data', 'image/png');

        expect($content->type())->toBe('image');
    });
});

describe('ToolResultContent', function () {
    it('creates with string result', function () {
        $content = new ToolResultContent('tool_123', 'Result text');

        expect($content->toolUseId)->toBe('tool_123');
        expect($content->result)->toBe('Result text');
        expect($content->isError)->toBeFalse();
    });

    it('creates with error flag', function () {
        $content = new ToolResultContent('tool_123', 'Error message', true);

        expect($content->isError)->toBeTrue();
    });

    it('converts to array with string result', function () {
        $content = new ToolResultContent('tool_123', 'Result text');

        $array = $content->toArray();

        expect($array['type'])->toBe('tool_result');
        expect($array['tool_use_id'])->toBe('tool_123');
        expect($array['content'])->toBe('Result text');
        expect($array['is_error'])->toBeFalse();
    });

    it('converts to array with array result', function () {
        $content = new ToolResultContent('tool_123', ['key' => 'value']);

        $array = $content->toArray();

        expect($array['content'])->toContain('"key"');
        expect($array['content'])->toContain('"value"');
    });

    it('converts to array with object result', function () {
        $content = new ToolResultContent('tool_123', (object) ['key' => 'value']);

        $array = $content->toArray();

        expect($array['content'])->toContain('"key"');
    });

    it('converts to array with scalar result', function () {
        $content = new ToolResultContent('tool_123', 42);

        $array = $content->toArray();

        expect($array['content'])->toBe('42');
    });

    it('returns correct type', function () {
        $content = new ToolResultContent('tool_123', 'Result');

        expect($content->type())->toBe('tool_result');
    });
});

describe('ToolUseContent', function () {
    it('creates with properties', function () {
        $content = new ToolUseContent('tool_1', 'get_weather', ['location' => 'NYC']);

        expect($content->id)->toBe('tool_1');
        expect($content->name)->toBe('get_weather');
        expect($content->input)->toBe(['location' => 'NYC']);
    });

    it('converts to array', function () {
        $content = new ToolUseContent('tool_1', 'search', ['query' => 'test']);

        $array = $content->toArray();

        expect($array['type'])->toBe('tool_use');
        expect($array['id'])->toBe('tool_1');
        expect($array['name'])->toBe('search');
        expect($array['input'])->toBe(['query' => 'test']);
    });

    it('returns correct type', function () {
        $content = new ToolUseContent('tool_1', 'test', []);

        expect($content->type())->toBe('tool_use');
    });
});

describe('StopReason', function () {
    it('checks if end turn is complete', function () {
        expect(\JayI\Cortex\Plugins\Chat\StopReason::EndTurn->isComplete())->toBeTrue();
        expect(\JayI\Cortex\Plugins\Chat\StopReason::MaxTokens->isComplete())->toBeFalse();
    });

    it('checks if max tokens is truncated', function () {
        expect(\JayI\Cortex\Plugins\Chat\StopReason::MaxTokens->isTruncated())->toBeTrue();
        expect(\JayI\Cortex\Plugins\Chat\StopReason::EndTurn->isTruncated())->toBeFalse();
    });

    it('checks if tool use requires execution', function () {
        expect(\JayI\Cortex\Plugins\Chat\StopReason::ToolUse->requiresToolExecution())->toBeTrue();
        expect(\JayI\Cortex\Plugins\Chat\StopReason::EndTurn->requiresToolExecution())->toBeFalse();
    });
});

describe('Usage', function () {
    it('creates zero usage', function () {
        $usage = \JayI\Cortex\Plugins\Chat\Usage::zero();

        expect($usage->inputTokens)->toBe(0);
        expect($usage->outputTokens)->toBe(0);
    });

    it('calculates total tokens', function () {
        $usage = new \JayI\Cortex\Plugins\Chat\Usage(100, 50);

        expect($usage->totalTokens())->toBe(150);
    });

    it('adds usages', function () {
        $usage1 = new \JayI\Cortex\Plugins\Chat\Usage(100, 50, 10, 5);
        $usage2 = new \JayI\Cortex\Plugins\Chat\Usage(200, 100, 20, 10);

        $combined = $usage1->add($usage2);

        expect($combined->inputTokens)->toBe(300);
        expect($combined->outputTokens)->toBe(150);
        expect($combined->cacheReadTokens)->toBe(30);
        expect($combined->cacheWriteTokens)->toBe(15);
    });

    it('handles null cache tokens when adding', function () {
        $usage1 = new \JayI\Cortex\Plugins\Chat\Usage(100, 50);
        $usage2 = new \JayI\Cortex\Plugins\Chat\Usage(200, 100);

        $combined = $usage1->add($usage2);

        expect($combined->cacheReadTokens)->toBeNull();
        expect($combined->cacheWriteTokens)->toBeNull();
    });
});

describe('StreamChunk', function () {
    it('creates text delta chunk', function () {
        $chunk = \JayI\Cortex\Plugins\Chat\StreamChunk::textDelta('Hello', 0);

        expect($chunk->type)->toBe(\JayI\Cortex\Plugins\Chat\StreamChunkType::TextDelta);
        expect($chunk->text)->toBe('Hello');
        expect($chunk->index)->toBe(0);
    });

    it('creates tool use start chunk', function () {
        $toolUse = new ToolUseContent('tool_1', 'search', []);
        $chunk = \JayI\Cortex\Plugins\Chat\StreamChunk::toolUseStart($toolUse, 1);

        expect($chunk->type)->toBe(\JayI\Cortex\Plugins\Chat\StreamChunkType::ToolUseStart);
        expect($chunk->toolUse)->toBe($toolUse);
    });

    it('creates message complete chunk', function () {
        $usage = new \JayI\Cortex\Plugins\Chat\Usage(100, 50);
        $chunk = \JayI\Cortex\Plugins\Chat\StreamChunk::messageComplete(
            $usage,
            \JayI\Cortex\Plugins\Chat\StopReason::EndTurn,
            2
        );

        expect($chunk->type)->toBe(\JayI\Cortex\Plugins\Chat\StreamChunkType::MessageComplete);
        expect($chunk->usage)->toBe($usage);
        expect($chunk->stopReason)->toBe(\JayI\Cortex\Plugins\Chat\StopReason::EndTurn);
    });

    it('checks if text chunk', function () {
        $textChunk = \JayI\Cortex\Plugins\Chat\StreamChunk::textDelta('text');
        $toolChunk = \JayI\Cortex\Plugins\Chat\StreamChunk::toolUseStart(new ToolUseContent('1', 'test', []));

        expect($textChunk->isText())->toBeTrue();
        expect($toolChunk->isText())->toBeFalse();
    });

    it('checks if tool use chunk', function () {
        $toolChunk = \JayI\Cortex\Plugins\Chat\StreamChunk::toolUseStart(new ToolUseContent('1', 'test', []));
        $textChunk = \JayI\Cortex\Plugins\Chat\StreamChunk::textDelta('text');

        expect($toolChunk->isToolUse())->toBeTrue();
        expect($textChunk->isToolUse())->toBeFalse();
    });

    it('checks if final chunk', function () {
        $finalChunk = \JayI\Cortex\Plugins\Chat\StreamChunk::messageComplete();
        $textChunk = \JayI\Cortex\Plugins\Chat\StreamChunk::textDelta('text');

        expect($finalChunk->isFinal())->toBeTrue();
        expect($textChunk->isFinal())->toBeFalse();
    });
});
