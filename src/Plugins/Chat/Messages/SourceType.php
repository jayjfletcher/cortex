<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Messages;

enum SourceType: string
{
    case Base64 = 'base64';
    case Url = 'url';
}
