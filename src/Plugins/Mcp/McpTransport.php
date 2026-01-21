<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

enum McpTransport: string
{
    /**
     * Standard input/output transport.
     */
    case Stdio = 'stdio';

    /**
     * Server-Sent Events transport.
     */
    case Sse = 'sse';

    /**
     * HTTP transport.
     */
    case Http = 'http';
}
