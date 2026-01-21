<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Plugin Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which plugins are enabled. Core plugins (schema, provider, chat)
    | are always loaded. Optional plugins can be enabled here.
    |
    */
    'plugins' => [
        'enabled' => [
            // Optional plugins - uncomment to enable
            // 'tool',
            // 'structured-output',
            // 'mcp',
            // 'agent',
            // 'workflow',
            // 'guardrail',
            // 'resilience',
            // 'prompt',
            // 'usage',
            // 'cache',
            // 'context',
        ],
        'disabled' => [
            // Explicitly disabled plugins
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | Enable multi-tenancy support for per-tenant provider configurations.
    |
    */
    'tenancy' => [
        'enabled' => false,
        'resolver' => null, // Class implementing TenantResolverContract
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Configure event dispatching for various Cortex operations.
    |
    */
    'events' => [
        'enabled' => true,
        'disabled' => [
            // Disable specific events
            // \JayI\Cortex\Events\Chat\ChatStreamChunk::class,
        ],
        'logging' => [
            'enabled' => false,
            'channel' => 'cortex',
            'level' => 'debug',
            'events' => [], // Empty = all events
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure LLM providers. Currently supports AWS Bedrock.
    |
    */
    'provider' => [
        'default' => 'bedrock',
        'providers' => [
            'bedrock' => [
                'driver' => 'bedrock',
                'region' => env('AWS_REGION', 'us-east-1'),
                'version' => 'latest',
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
                'default_model' => env('CORTEX_DEFAULT_MODEL', 'anthropic.claude-3-5-sonnet-20241022-v2:0'),
                'models' => [
                    // Add custom model definitions here
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default chat options and broadcasting settings.
    |
    */
    'chat' => [
        'default_model' => null, // Use provider default
        'default_options' => [
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ],
        'broadcasting' => [
            'driver' => 'echo', // 'echo' or 'sse'
            'echo' => [
                'connection' => null, // Use default
            ],
            'sse' => [
                'retry' => 3000, // Retry interval in ms
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tool discovery and defaults.
    |
    */
    'tool' => [
        'discovery' => [
            'enabled' => true,
            'paths' => [
                app_path('Tools'),
            ],
        ],
        'defaults' => [
            'timeout' => 30,
        ],
        'tools' => [
            // Register specific tools
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Structured Output Configuration
    |--------------------------------------------------------------------------
    |
    | Configure structured output handling.
    |
    */
    'structured_output' => [
        'strategy' => 'auto', // 'auto', 'native', 'json_mode', 'prompt'
        'validation' => [
            'enabled' => true,
            'throw_on_invalid' => false,
        ],
        'retry' => [
            'enabled' => true,
            'max_attempts' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    |
    | Configure agent discovery and defaults.
    |
    */
    'agent' => [
        'discovery' => [
            'enabled' => true,
            'paths' => [
                app_path('Agents'),
            ],
        ],
        'defaults' => [
            'max_iterations' => 10,
            'loop_strategy' => 'react',
            'memory' => 'sliding_window',
        ],
        'memory' => [
            'sliding_window' => [
                'size' => 10,
            ],
            'token_limit' => [
                'max_tokens' => 4000,
            ],
        ],
        'async' => [
            'queue' => 'agents',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow Configuration
    |--------------------------------------------------------------------------
    |
    | Configure workflow discovery, persistence, and async execution.
    |
    */
    'workflow' => [
        'discovery' => [
            'enabled' => true,
            'paths' => [
                app_path('Workflows'),
            ],
        ],
        'persistence' => [
            'driver' => 'database', // 'database', 'cache'
            'table' => 'cortex_workflow_states',
            'ttl' => 86400 * 7, // 7 days for cache driver
        ],
        'async' => [
            'queue' => 'workflows',
            'timeout' => 3600, // 1 hour max
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Model Context Protocol servers.
    |
    */
    'mcp' => [
        'servers' => [
            // Stdio transport example (local process):
            // 'filesystem' => [
            //     'command' => 'npx',
            //     'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/path/to/dir'],
            //     'transport' => 'stdio',
            // ],

            // HTTP transport example (remote server):
            // 'remote-server' => [
            //     'transport' => 'http',
            //     'url' => 'https://mcp-server.example.com/rpc',
            //     'headers' => [
            //         'Authorization' => 'Bearer your-api-key',
            //     ],
            //     'timeout' => 30,
            //     'verify_ssl' => true,
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guardrail Configuration
    |--------------------------------------------------------------------------
    |
    | Configure content guardrails.
    |
    */
    'guardrail' => [
        'discovery' => [
            'enabled' => true,
            'paths' => [
                app_path('Guardrails'),
            ],
        ],
        'guardrails' => [
            // 'bedrock-default' => [
            //     'driver' => 'bedrock',
            //     'guardrail_id' => env('BEDROCK_GUARDRAIL_ID'),
            //     'version' => env('BEDROCK_GUARDRAIL_VERSION', 'DRAFT'),
            // ],
        ],
        'default' => [
            // Apply these guardrails to all chat requests
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resilience Configuration
    |--------------------------------------------------------------------------
    |
    | Configure fault tolerance for LLM API calls.
    |
    */
    'resilience' => [
        'enabled' => true,
        'default' => [
            'retry' => [
                'attempts' => 3,
                'delay' => 1000,
                'multiplier' => 2.0,
            ],
            'timeout' => 30000,
        ],
        'providers' => [
            // Provider-specific resilience settings
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt Configuration
    |--------------------------------------------------------------------------
    |
    | Configure prompt templating and discovery.
    |
    */
    'prompt' => [
        'discovery' => [
            'enabled' => true,
            'paths' => [
                resource_path('prompts'),
            ],
        ],
        'caching' => [
            'enabled' => true,
            'ttl' => 3600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Configure token usage and cost tracking.
    |
    */
    'usage' => [
        'enabled' => true,
        'tracking' => [
            'driver' => 'database', // 'database', 'redis', 'null'
            'table' => 'cortex_usage',
        ],
        'budgets' => [
            // 'global' => [
            //     'type' => 'cost',
            //     'limit' => 1000.00,
            //     'period' => 'monthly',
            // ],
        ],
        'alerts' => [
            'thresholds' => [0.5, 0.75, 0.9, 1.0],
            'channels' => ['mail'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure response caching.
    |
    */
    'cache' => [
        'enabled' => false,
        'strategy' => 'exact', // 'exact', 'semantic'
        'store' => 'redis',
        'ttl' => 3600,
        'skip_if' => [
            'has_tools' => true,
            'temperature_above' => 0.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic context window management.
    |
    */
    'context' => [
        'strategy' => 'truncate', // 'truncate', 'summarize', 'importance'
        'reserve_tokens' => 4096, // Reserve for response
        'truncate' => [
            'preserve_system' => true,
            'preserve_recent' => 2,
        ],
        'summarize' => [
            'threshold' => 10,
            'agent' => null, // Agent ID for summarization
        ],
    ],
];
