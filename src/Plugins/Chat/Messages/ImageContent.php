<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Messages;

use InvalidArgumentException;

class ImageContent extends Content
{
    public function __construct(
        public readonly string $source,
        public readonly string $mediaType,
        public readonly SourceType $sourceType = SourceType::Base64,
    ) {}

    /**
     * Create from base64 encoded data.
     */
    public static function fromBase64(string $data, string $mediaType): static
    {
        return new static($data, $mediaType, SourceType::Base64);
    }

    /**
     * Create from URL.
     */
    public static function fromUrl(string $url): static
    {
        // Determine media type from URL extension
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $mediaType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return new static($url, $mediaType, SourceType::Url);
    }

    /**
     * Create from file path.
     */
    public static function fromPath(string $path): static
    {
        if (! file_exists($path)) {
            throw new InvalidArgumentException("Image file not found: {$path}");
        }

        $data = base64_encode((string) file_get_contents($path));
        $mediaType = mime_content_type($path) ?: 'image/jpeg';

        return new static($data, $mediaType, SourceType::Base64);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'image',
            'source' => $this->source,
            'media_type' => $this->mediaType,
            'source_type' => $this->sourceType->value,
        ];
    }

    /**
     * Get the content type.
     */
    public function type(): string
    {
        return 'image';
    }
}
