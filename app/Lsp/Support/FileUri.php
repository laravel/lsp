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
            : new self($target . '#L' . max(1, $line));
    }

    /**
     * Get a path relative to this URI.
     */
    public function relativePath(string $path): string
    {
        $basePath = rtrim(self::normalizePath($this->path()), '/');

        if ($basePath === '') {
            return $path;
        }

        $normalized = self::normalizePath($path);

        // The common case: the client sent a path in the same form the root was
        // registered in, so neither side needs resolving.
        if (($relative = self::relativeTo($normalized, $basePath)) !== null) {
            return $relative;
        }

        // Otherwise the root, the path, or both may be reached through a symlink.
        // Resolving one side is not enough, since either may be the symlinked one.
        $basePaths = [$basePath];

        if (($resolvedBase = realpath($this->path())) !== false) {
            $basePaths[] = rtrim(self::normalizePath($resolvedBase), '/');
        }

        if (($resolvedPath = realpath($path)) !== false) {
            $normalized = self::normalizePath($resolvedPath);
        }

        foreach ($basePaths as $candidate) {
            if (($relative = self::relativeTo($normalized, $candidate)) !== null) {
                return $relative;
            }
        }

        return $path;
    }

    /**
     * Get the given normalized path relative to a base path, if it is inside it.
     */
    protected static function relativeTo(string $path, string $basePath): ?string
    {
        if ($path === $basePath) {
            return '';
        }

        return str_starts_with($path, $basePath . '/')
            ? substr($path, strlen($basePath) + 1)
            : null;
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
        return new self('file://' . self::encodePath($path));
    }

    /**
     * Get the file URI path as a decoded filesystem path.
     */
    protected function uriPath(): string
    {
        $path = rawurldecode((string) parse_url($this->uri, PHP_URL_PATH));

        if (preg_match('#^/[A-Za-z]:(?:/|$)#', $path) === 1) {
            return substr($path, 1);
        }

        return $path;
    }

    /**
     * Normalize a filesystem path for comparison across platforms.
     */
    protected static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (preg_match('/^[A-Za-z]:(?:\/|$)/', $path) === 1) {
            return strtoupper($path[0]) . substr($path, 1);
        }

        return $path;
    }

    /**
     * Encode a filesystem path for use in a file URI.
     */
    protected static function encodePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = '/' . ltrim($path, '/');

        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
