<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use JayI\Cortex\Exceptions\McpException;
use JayI\Cortex\Plugins\Mcp\McpPrompt;
use JayI\Cortex\Plugins\Mcp\McpPromptArgument;
use JayI\Cortex\Plugins\Mcp\McpPromptMessage;
use JayI\Cortex\Plugins\Mcp\McpPromptResult;
use JayI\Cortex\Plugins\Mcp\McpRegistry;
use JayI\Cortex\Plugins\Mcp\McpResource;
use JayI\Cortex\Plugins\Mcp\McpResourceContent;
use JayI\Cortex\Plugins\Mcp\McpTransport;
use JayI\Cortex\Plugins\Mcp\Servers\HttpMcpServer;

describe('McpTransport', function () {
    it('has correct transport types', function () {
        expect(McpTransport::Stdio->value)->toBe('stdio');
        expect(McpTransport::Sse->value)->toBe('sse');
        expect(McpTransport::Http->value)->toBe('http');
    });
});

describe('McpResource', function () {
    it('creates a resource', function () {
        $resource = new McpResource(
            uri: 'file:///path/to/file.txt',
            name: 'file.txt',
            description: 'A text file',
            mimeType: 'text/plain',
        );

        expect($resource->uri)->toBe('file:///path/to/file.txt');
        expect($resource->name)->toBe('file.txt');
        expect($resource->description)->toBe('A text file');
        expect($resource->mimeType)->toBe('text/plain');
    });
});

describe('McpResourceContent', function () {
    it('creates resource content', function () {
        $content = new McpResourceContent(
            uri: 'file:///test.txt',
            content: 'Hello, World!',
            mimeType: 'text/plain',
        );

        expect($content->uri)->toBe('file:///test.txt');
        expect($content->content)->toBe('Hello, World!');
        expect($content->isText())->toBeTrue();
        expect($content->isBinary())->toBeFalse();
    });

    it('detects binary content', function () {
        $content = new McpResourceContent(
            uri: 'file:///image.png',
            content: base64_encode('binary data'),
            mimeType: 'image/png',
        );

        expect($content->isText())->toBeFalse();
        expect($content->isBinary())->toBeTrue();
    });

    it('parses json content', function () {
        $content = new McpResourceContent(
            uri: 'file:///data.json',
            content: '{"key": "value"}',
            mimeType: 'application/json',
        );

        expect($content->isText())->toBeTrue();
        expect($content->json())->toBe(['key' => 'value']);
    });

    it('returns null for invalid json', function () {
        $content = new McpResourceContent(
            uri: 'file:///data.txt',
            content: 'not json',
            mimeType: 'text/plain',
        );

        expect($content->json())->toBeNull();
    });
});

describe('McpPrompt', function () {
    it('creates a prompt', function () {
        $prompt = new McpPrompt(
            name: 'greeting',
            description: 'A greeting prompt',
            arguments: [
                new McpPromptArgument(name: 'name', description: 'User name', required: true),
            ],
        );

        expect($prompt->name)->toBe('greeting');
        expect($prompt->description)->toBe('A greeting prompt');
        expect($prompt->arguments)->toHaveCount(1);
        expect($prompt->arguments[0]->name)->toBe('name');
        expect($prompt->arguments[0]->required)->toBeTrue();
    });
});

describe('McpPromptResult', function () {
    it('creates a prompt result', function () {
        $result = new McpPromptResult(
            description: 'A greeting',
            messages: [
                new McpPromptMessage(role: 'user', content: 'Hello!'),
                new McpPromptMessage(role: 'assistant', content: 'Hi there!'),
            ],
        );

        expect($result->description)->toBe('A greeting');
        expect($result->messages)->toHaveCount(2);
    });

    it('converts to message collection', function () {
        $result = new McpPromptResult(
            messages: [
                new McpPromptMessage(role: 'system', content: 'You are helpful.'),
                new McpPromptMessage(role: 'user', content: 'Hello!'),
            ],
        );

        $collection = $result->toMessageCollection();

        expect($collection->count())->toBe(2);
    });

    it('gets combined text', function () {
        $result = new McpPromptResult(
            messages: [
                new McpPromptMessage(role: 'user', content: 'Line 1'),
                new McpPromptMessage(role: 'assistant', content: 'Line 2'),
            ],
        );

        expect($result->text())->toBe("Line 1\nLine 2");
    });
});

describe('McpPromptMessage', function () {
    it('converts to chat message', function () {
        $userMessage = new McpPromptMessage(role: 'user', content: 'Hello');
        $assistantMessage = new McpPromptMessage(role: 'assistant', content: 'Hi');
        $systemMessage = new McpPromptMessage(role: 'system', content: 'System');

        expect($userMessage->toMessage()->text())->toBe('Hello');
        expect($assistantMessage->toMessage()->text())->toBe('Hi');
        expect($systemMessage->toMessage()->text())->toBe('System');
    });
});

describe('McpRegistry', function () {
    it('starts empty', function () {
        $registry = new McpRegistry;

        expect($registry->all()->isEmpty())->toBeTrue();
    });

    it('throws when server not found', function () {
        $registry = new McpRegistry;

        expect(fn () => $registry->get('nonexistent'))
            ->toThrow(McpException::class);
    });

    it('checks server existence', function () {
        $registry = new McpRegistry;

        expect($registry->has('some-server'))->toBeFalse();
    });
});

describe('McpException', function () {
    it('creates server not found exception', function () {
        $exception = McpException::serverNotFound('my-server');

        expect($exception->getMessage())->toContain('my-server');
        expect($exception->context()['server_id'])->toBe('my-server');
    });

    it('creates connection failed exception', function () {
        $exception = McpException::connectionFailed('my-server', 'Connection refused');

        expect($exception->getMessage())->toContain('my-server');
        expect($exception->getMessage())->toContain('Connection refused');
    });

    it('creates not connected exception', function () {
        $exception = McpException::notConnected('my-server');

        expect($exception->getMessage())->toContain('not connected');
    });

    it('creates tool not found exception', function () {
        $exception = McpException::toolNotFound('my-server', 'my-tool');

        expect($exception->context()['server_id'])->toBe('my-server');
        expect($exception->context()['tool_name'])->toBe('my-tool');
    });

    it('creates resource not found exception', function () {
        $exception = McpException::resourceNotFound('my-server', 'file:///test.txt');

        expect($exception->context()['resource_uri'])->toBe('file:///test.txt');
    });

    it('creates prompt not found exception', function () {
        $exception = McpException::promptNotFound('my-server', 'my-prompt');

        expect($exception->context()['prompt_name'])->toBe('my-prompt');
    });

    it('creates protocol error exception', function () {
        $exception = McpException::protocolError('my-server', 'Invalid response');

        expect($exception->getMessage())->toContain('protocol error');
    });
});

describe('HttpMcpServer', function () {
    it('creates server from config', function () {
        $server = HttpMcpServer::fromConfig('test-server', [
            'name' => 'Test Server',
            'url' => 'https://example.com/mcp',
            'headers' => ['Authorization' => 'Bearer token'],
            'timeout' => 60,
            'verify_ssl' => false,
        ]);

        expect($server->id())->toBe('test-server');
        expect($server->name())->toBe('Test Server');
        expect($server->transport())->toBe(McpTransport::Http);
        expect($server->isConnected())->toBeFalse();
    });

    it('uses id as name when name not provided', function () {
        $server = HttpMcpServer::fromConfig('my-server', [
            'url' => 'https://example.com/mcp',
        ]);

        expect($server->name())->toBe('my-server');
    });

    it('uses default values for optional config', function () {
        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        expect($server->id())->toBe('test');
        expect($server->transport())->toBe(McpTransport::Http);
    });

    it('connects to server successfully', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['protocolVersion' => '2024-11-05']])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => []]]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        $server->connect();

        expect($server->isConnected())->toBeTrue();
    });

    it('throws when connection fails', function () {
        Http::fake([
            '*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        expect(fn () => $server->connect())
            ->toThrow(McpException::class);
    });

    it('throws when accessing tools while not connected', function () {
        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        expect(fn () => $server->tools())
            ->toThrow(McpException::class);
    });

    it('throws when accessing resources while not connected', function () {
        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        expect(fn () => $server->resources())
            ->toThrow(McpException::class);
    });

    it('throws when accessing prompts while not connected', function () {
        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        expect(fn () => $server->prompts())
            ->toThrow(McpException::class);
    });

    it('disconnects and clears state', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => []]]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        $server->connect();
        expect($server->isConnected())->toBeTrue();

        $server->disconnect();
        expect($server->isConnected())->toBeFalse();
    });

    it('returns cached tools after connection', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => [
                    ['name' => 'test-tool', 'description' => 'A test tool'],
                ]]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => []]]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        $server->connect();
        $tools = $server->tools();

        expect($tools->count())->toBe(1);
        expect($tools->get('test-tool'))->not->toBeNull();
        expect($tools->get('test-tool')->name())->toBe('test-tool');
    });

    it('returns cached resources after connection', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => [
                    ['uri' => 'file:///test.txt', 'name' => 'test.txt'],
                ]]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => []]]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        $server->connect();
        $resources = $server->resources();

        expect($resources)->toHaveCount(1);
        expect($resources[0]->uri)->toBe('file:///test.txt');
    });

    it('returns cached prompts after connection', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => [
                    ['name' => 'greeting', 'description' => 'A greeting prompt'],
                ]]]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        $server->connect();
        $prompts = $server->prompts();

        expect($prompts)->toHaveCount(1);
        expect($prompts[0]->name)->toBe('greeting');
    });

    it('reads resource content', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 6, 'result' => ['contents' => [
                    ['uri' => 'file:///test.txt', 'text' => 'Hello, World!', 'mimeType' => 'text/plain'],
                ]]]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        $server->connect();
        $content = $server->readResource('file:///test.txt');

        expect($content->uri)->toBe('file:///test.txt');
        expect($content->content)->toBe('Hello, World!');
        expect($content->mimeType)->toBe('text/plain');
    });

    it('throws when resource not found', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 6, 'result' => ['contents' => []]]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        $server->connect();

        expect(fn () => $server->readResource('file:///missing.txt'))
            ->toThrow(McpException::class);
    });

    it('gets prompt result', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 6, 'result' => [
                    'description' => 'A greeting',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello!'],
                    ],
                ]]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        $server->connect();
        $result = $server->getPrompt('greeting', ['name' => 'World']);

        expect($result->description)->toBe('A greeting');
        expect($result->messages)->toHaveCount(1);
        expect($result->messages[0]->content)->toBe('Hello!');
    });

    it('handles protocol error response', function () {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'error' => ['code' => -32600, 'message' => 'Invalid Request'],
            ]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        expect(fn () => $server->connect())
            ->toThrow(McpException::class, 'Invalid Request');
    });

    it('skips reconnection when already connected', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => []]]),
        ]);

        $server = HttpMcpServer::fromConfig('test', [
            'url' => 'https://example.com/mcp',
        ]);

        $server->connect();
        $server->connect(); // Should not make additional requests

        Http::assertSentCount(5);
    });
});
