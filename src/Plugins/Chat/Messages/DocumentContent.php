<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Messages;

use InvalidArgumentException;

class DocumentContent extends Content
{
    public function __construct(
        public readonly string $source,
        public readonly string $mediaType,
        public readonly ?string $name = null,
    ) {}

    /**
     * Create from base64 encoded data.
     */
    public static function fromBase64(string $data, string $mediaType, ?string $name = null): static
    {
        return new static($data, $mediaType, $name);
    }

    /**
     * Create from file path.
     */
    public static function fromPath(string $path): static
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("Document file not found: {$path}");
        }

        $data = base64_encode((string) file_get_contents($path));
        $mediaType = mime_content_type($path) ?: 'application/octet-stream';
        $name = basename($path);

        return new static($data, $mediaType, $name);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = [
            'type' => 'document',
            'source' => $this->source,
            'media_type' => $this->mediaType,
        ];

        if ($this->name !== null) {
            $array['name'] = $this->name;
        }

        return $array;
    }

    /**
     * Get the content type.
     */
    public function type(): string
    {
        return 'document';
    }
}
