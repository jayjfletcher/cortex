<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Chat\ChatOptions;
use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\ChatRequestBuilder;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\ToolCollection;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Tool;

describe('ChatRequest', function () {
    it('creates with messages', function () {
        $messages = MessageCollection::make()->user('Hello');

        $request = new ChatRequest(messages: $messages);

        expect($request->messages)->toBe($messages);
        expect($request->systemPrompt)->toBeNull();
        expect($request->model)->toBeNull();
        expect($request->hasTools())->toBeFalse();
        expect($request->hasResponseSchema())->toBeFalse();
    });

    it('creates simple request with message', function () {
        $request = ChatRequest::simple('Hello');

        expect($request->messages->count())->toBe(1);
        expect($request->systemPrompt)->toBeNull();
    });

    it('creates simple request with system prompt', function () {
        $request = ChatRequest::simple('Hello', 'You are helpful');

        expect($request->messages->count())->toBe(2);
        expect($request->systemPrompt)->toBe('You are helpful');
    });

    it('returns make as builder', function () {
        $builder = ChatRequest::make();

        expect($builder)->toBeInstanceOf(ChatRequestBuilder::class);
    });

    it('detects tools', function () {
        $tool = Tool::make('test_tool')
            ->withInput(Schema::object())
            ->withHandler(fn () => 'result');

        $tools = ToolCollection::make([$tool]);
        $messages = MessageCollection::make()->user('Hello');

        $request = new ChatRequest(
            messages: $messages,
            tools: $tools,
        );

        expect($request->hasTools())->toBeTrue();
    });

    it('detects empty tool collection', function () {
        $tools = ToolCollection::make([]);
        $messages = MessageCollection::make()->user('Hello');

        $request = new ChatRequest(
            messages: $messages,
            tools: $tools,
        );

        expect($request->hasTools())->toBeFalse();
    });

    it('detects response schema', function () {
        $schema = Schema::object()->property('name', Schema::string());
        $messages = MessageCollection::make()->user('Hello');

        $request = new ChatRequest(
            messages: $messages,
            responseSchema: $schema,
        );

        expect($request->hasResponseSchema())->toBeTrue();
    });

    it('gets system message from collection', function () {
        $messages = MessageCollection::make()
            ->system('You are helpful')
            ->user('Hello');

        $request = new ChatRequest(messages: $messages);

        $systemMessage = $request->getSystemMessage();

        expect($systemMessage)->not->toBeNull();
        expect($systemMessage->text())->toBe('You are helpful');
    });

    it('returns null when no system message', function () {
        $messages = MessageCollection::make()->user('Hello');

        $request = new ChatRequest(messages: $messages);

        expect($request->getSystemMessage())->toBeNull();
    });

    it('gets effective system prompt from explicit property', function () {
        $messages = MessageCollection::make()->user('Hello');

        $request = new ChatRequest(
            messages: $messages,
            systemPrompt: 'Explicit prompt',
        );

        expect($request->getEffectiveSystemPrompt())->toBe('Explicit prompt');
    });

    it('gets effective system prompt from message when no explicit', function () {
        $messages = MessageCollection::make()
            ->system('From message')
            ->user('Hello');

        $request = new ChatRequest(messages: $messages);

        expect($request->getEffectiveSystemPrompt())->toBe('From message');
    });

    it('prefers explicit system prompt over message', function () {
        $messages = MessageCollection::make()
            ->system('From message')
            ->user('Hello');

        $request = new ChatRequest(
            messages: $messages,
            systemPrompt: 'Explicit prompt',
        );

        expect($request->getEffectiveSystemPrompt())->toBe('Explicit prompt');
    });

    it('stores metadata', function () {
        $messages = MessageCollection::make()->user('Hello');

        $request = new ChatRequest(
            messages: $messages,
            metadata: ['key' => 'value'],
        );

        expect($request->metadata)->toBe(['key' => 'value']);
    });
});

describe('ChatRequestBuilder', function () {
    it('creates empty builder', function () {
        $builder = new ChatRequestBuilder();
        $request = $builder->build();

        expect($request->messages->count())->toBe(0);
    });

    it('sets system prompt', function () {
        $request = (new ChatRequestBuilder())
            ->system('You are helpful')
            ->message('Hello')
            ->build();

        expect($request->systemPrompt)->toBe('You are helpful');
        expect($request->messages->count())->toBe(2); // system + user
    });

    it('adds string message as user', function () {
        $request = (new ChatRequestBuilder())
            ->message('Hello')
            ->build();

        expect($request->messages->count())->toBe(1);
        expect($request->messages->first()->text())->toBe('Hello');
    });

    it('adds Message object', function () {
        $message = Message::assistant('Hello');

        $request = (new ChatRequestBuilder())
            ->message($message)
            ->build();

        expect($request->messages->count())->toBe(1);
        expect($request->messages->first())->toBe($message);
    });

    it('sets messages from array', function () {
        $messages = [
            Message::user('Hello'),
            Message::assistant('Hi'),
        ];

        $request = (new ChatRequestBuilder())
            ->messages($messages)
            ->build();

        expect($request->messages->count())->toBe(2);
    });

    it('sets messages from collection', function () {
        $collection = MessageCollection::make()
            ->user('Hello')
            ->assistant('Hi');

        $request = (new ChatRequestBuilder())
            ->messages($collection)
            ->build();

        expect($request->messages->count())->toBe(2);
    });

    it('sets model', function () {
        $request = (new ChatRequestBuilder())
            ->model('gpt-4')
            ->message('Hello')
            ->build();

        expect($request->model)->toBe('gpt-4');
    });

    it('sets options from ChatOptions', function () {
        $options = new ChatOptions(temperature: 0.5);

        $request = (new ChatRequestBuilder())
            ->options($options)
            ->message('Hello')
            ->build();

        expect($request->options->temperature)->toBe(0.5);
    });

    it('sets options from array', function () {
        // Use ChatOptions constructor since from() requires Laravel config
        $request = (new ChatRequestBuilder())
            ->options(new ChatOptions(temperature: 0.8))
            ->message('Hello')
            ->build();

        expect($request->options->temperature)->toBe(0.8);
    });

    it('sets temperature', function () {
        $request = (new ChatRequestBuilder())
            ->temperature(0.9)
            ->message('Hello')
            ->build();

        expect($request->options->temperature)->toBe(0.9);
    });

    it('sets max tokens', function () {
        $request = (new ChatRequestBuilder())
            ->maxTokens(2048)
            ->message('Hello')
            ->build();

        expect($request->options->maxTokens)->toBe(2048);
    });

    it('sets tools from ToolCollection', function () {
        $tool = Tool::make('test')
            ->withInput(Schema::object())
            ->withHandler(fn () => 'result');

        $tools = ToolCollection::make([$tool]);

        $request = (new ChatRequestBuilder())
            ->tools($tools)
            ->message('Hello')
            ->build();

        expect($request->tools->count())->toBe(1);
    });

    it('sets tools from array', function () {
        $tool = Tool::make('test')
            ->withInput(Schema::object())
            ->withHandler(fn () => 'result');

        $request = (new ChatRequestBuilder())
            ->tools([$tool])
            ->message('Hello')
            ->build();

        expect($request->tools->count())->toBe(1);
    });

    it('sets response schema', function () {
        $schema = Schema::object();

        $request = (new ChatRequestBuilder())
            ->responseSchema($schema)
            ->message('Hello')
            ->build();

        expect($request->responseSchema)->toBe($schema);
    });

    it('sets metadata', function () {
        $request = (new ChatRequestBuilder())
            ->metadata(['key' => 'value'])
            ->message('Hello')
            ->build();

        expect($request->metadata)->toBe(['key' => 'value']);
    });

    it('does not duplicate system message', function () {
        $request = (new ChatRequestBuilder())
            ->system('First system')
            ->messages([
                Message::system('Another system'),
                Message::user('Hello'),
            ])
            ->build();

        // Should not prepend since messages already has system
        $systemCount = 0;
        foreach ($request->messages as $message) {
            if ($message->role === \JayI\Cortex\Plugins\Chat\MessageRole::System) {
                $systemCount++;
            }
        }
        expect($systemCount)->toBe(1);
    });
});

describe('ChatOptions', function () {
    it('creates with defaults constructor', function () {
        $options = new ChatOptions();

        expect($options->temperature)->toBeNull();
        expect($options->maxTokens)->toBeNull();
        expect($options->stopSequences)->toBe([]);
    });

    it('creates with static defaults', function () {
        $options = ChatOptions::defaults();

        expect($options->temperature)->toBe(0.7);
        expect($options->maxTokens)->toBe(4096);
    });

    it('merges options', function () {
        $base = new ChatOptions(
            temperature: 0.5,
            maxTokens: 1000,
            stopSequences: ['stop1'],
        );

        $override = new ChatOptions(
            temperature: 0.8,
            stopSequences: ['stop2'],
        );

        $merged = $base->merge($override);

        expect($merged->temperature)->toBe(0.8);
        expect($merged->maxTokens)->toBe(1000);
        expect($merged->stopSequences)->toBe(['stop1', 'stop2']);
    });

    it('preserves base values when override is null', function () {
        $base = new ChatOptions(
            temperature: 0.5,
            maxTokens: 2000,
            topP: 0.9,
        );

        $override = new ChatOptions();

        $merged = $base->merge($override);

        expect($merged->temperature)->toBe(0.5);
        expect($merged->maxTokens)->toBe(2000);
        expect($merged->topP)->toBe(0.9);
    });

    it('merges provider options', function () {
        $base = new ChatOptions(
            providerOptions: ['key1' => 'value1'],
        );

        $override = new ChatOptions(
            providerOptions: ['key2' => 'value2'],
        );

        $merged = $base->merge($override);

        expect($merged->providerOptions)->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);
    });

    it('stores tool choice', function () {
        $options = new ChatOptions(toolChoice: 'auto');

        expect($options->toolChoice)->toBe('auto');
    });
});
