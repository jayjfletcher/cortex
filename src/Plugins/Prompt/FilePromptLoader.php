<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt;

use Illuminate\Support\Facades\File;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptRegistryContract;
use Symfony\Component\Yaml\Yaml;

class FilePromptLoader
{
    public function __construct(
        protected PromptRegistryContract $registry,
    ) {}

    public function loadFromPath(string $path): void
    {
        if (! File::isDirectory($path)) {
            return;
        }

        foreach (File::directories($path) as $promptDir) {
            $this->loadPromptDirectory($promptDir);
        }

        // Also load standalone prompt files
        foreach (File::glob($path . '/*.blade.php') as $promptFile) {
            $this->loadPromptFile($promptFile);
        }
    }

    protected function loadPromptDirectory(string $dir): void
    {
        $metadataFile = $dir . '/prompt.yaml';
        $metadata = [];

        if (File::exists($metadataFile)) {
            $metadata = Yaml::parseFile($metadataFile) ?? [];
        }

        $id = $metadata['id'] ?? basename($dir);
        $name = $metadata['name'] ?? $id;
        $requiredVariables = $metadata['required_variables'] ?? [];
        $defaults = $metadata['defaults'] ?? [];

        // Load all version files (v1.blade.php, v1.0.blade.php, v1.0.0.blade.php)
        $versionFiles = File::glob($dir . '/v*.blade.php');

        if (empty($versionFiles)) {
            // Check for a default template.blade.php
            $defaultTemplate = $dir . '/template.blade.php';
            if (File::exists($defaultTemplate)) {
                $versionFiles = [$defaultTemplate];
            }
        }

        foreach ($versionFiles as $versionFile) {
            $version = $this->extractVersion($versionFile);
            $template = File::get($versionFile);

            $prompt = new Prompt(
                id: $id,
                template: $template,
                requiredVariables: $requiredVariables,
                defaults: $defaults,
                version: $version,
                name: $name,
                metadata: $metadata,
            );

            $this->registry->register($prompt);
        }
    }

    protected function loadPromptFile(string $file): void
    {
        $basename = basename($file, '.blade.php');
        $template = File::get($file);

        // Parse any frontmatter-style metadata from the template
        $metadata = $this->extractFrontmatter($template);
        $template = $this->stripFrontmatter($template);

        $prompt = new Prompt(
            id: $metadata['id'] ?? $basename,
            template: $template,
            requiredVariables: $metadata['required_variables'] ?? [],
            defaults: $metadata['defaults'] ?? [],
            version: $metadata['version'] ?? '1.0.0',
            name: $metadata['name'] ?? $basename,
            metadata: $metadata,
        );

        $this->registry->register($prompt);
    }

    protected function extractVersion(string $filename): string
    {
        $basename = basename($filename, '.blade.php');

        // Handle template.blade.php as default version
        if ($basename === 'template') {
            return '1.0.0';
        }

        // Extract version from filename like v1, v2, v1.0, v1.0.0
        if (preg_match('/^v?(\d+(?:\.\d+(?:\.\d+)?)?)$/', $basename, $matches)) {
            $version = $matches[1];

            // Normalize to semver format
            $parts = explode('.', $version);
            while (count($parts) < 3) {
                $parts[] = '0';
            }

            return implode('.', $parts);
        }

        return '1.0.0';
    }

    protected function extractFrontmatter(string $content): array
    {
        // Check for YAML frontmatter between ---
        if (preg_match('/^---\s*\n(.+?)\n---\s*\n/s', $content, $matches)) {
            try {
                return Yaml::parse($matches[1]) ?? [];
            } catch (\Exception) {
                return [];
            }
        }

        // Check for PHP comment metadata
        if (preg_match('/^{{--\s*(.+?)\s*--}}/s', $content, $matches)) {
            try {
                return Yaml::parse($matches[1]) ?? [];
            } catch (\Exception) {
                return [];
            }
        }

        return [];
    }

    protected function stripFrontmatter(string $content): string
    {
        // Strip YAML frontmatter
        $content = preg_replace('/^---\s*\n.+?\n---\s*\n/s', '', $content);

        // Strip PHP comment metadata
        $content = preg_replace('/^{{--\s*.+?\s*--}}\s*/s', '', $content);

        return trim($content);
    }
}
