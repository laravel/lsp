<?php

declare(strict_types=1);

namespace App\Lsp\Support;

use JsonSerializable;

use function Illuminate\Filesystem\join_paths;

class FileUri implements JsonSerializable
{
    /**
     * Create a new file URI instance.
     */
    public function __construct(
        protected string $uri,
    ) {}

    /**
     * Create a file URI instance from the given string.
     */
    public static function of(string $uri): self
    {
        return new self($uri);
    }

    /**
     * Get a URI instance with the given paths appended.
     */
    public function joinPaths(string ...$paths): self
    {
        return self::fromPath(join_paths($this->uriPath(), ...$paths));
    }

    /**
     * Get the decoded file path, optionally with a relative path appended.
     */
    public function path(string $path = ''): string
    {
        $basePath = $this->uriPath();

        return $path === '' ? $basePath : join_paths($basePath, $path);
    }

    /**
     * Create a URI that targets a specific path and line.
     */
    public function target(string $path, ?int $line = null): self
    {
        $target = $this->joinPaths($path);

        return $line === null
            ? $target
            : new self($target.'#L'.max(1, $line));
    }

    /**
     * Get a path relative to this URI.
     */
    public function relativePath(string $path): string
    {
        $basePath = $this->path();

        if (!str_contains($path, $basePath)) {
            return $path;
        }

        return ltrim(str_replace($basePath, '', realpath($path) ?: $path), DIRECTORY_SEPARATOR);
    }

    /**
     * Convert the URI to a string.
     */
    public function __toString(): string
    {
        return $this->uri;
    }

    /**
     * Convert the URI to a JSON-serializable string.
     */
    public function jsonSerialize(): string
    {
        return $this->uri;
    }

    /**
     * Create a file URI instance from the given path.
     */
    public static function fromPath(string $path): self
    {
        return new self('file://'.self::encodePath($path));
    }

    /**
     * Get the file URI path as a decoded filesystem path.
     */
    protected function uriPath(): string
    {
        return rawurldecode((string) parse_url($this->uri, PHP_URL_PATH));
    }

    /**
     * Encode a filesystem path for use in a file URI.
     */
    protected static function encodePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = '/'.ltrim($path, '/');

        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
