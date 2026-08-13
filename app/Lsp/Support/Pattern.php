<?php

declare(strict_types=1);

namespace App\Lsp\Support;

use Symfony\Component\Finder\Glob;

class Pattern
{
    /**
     * The compiled expressions, keyed by file watcher pattern.
     *
     * @var array<string, string>
     */
    protected static array $expressions = [];

    /**
     * Determine if the given path matches a file watcher pattern.
     */
    public static function matches(string $path, string $pattern): bool
    {
        return preg_match(self::expression($pattern), self::normalize($path)) === 1;
    }

    /**
     * Get the compiled expression for the given file watcher pattern.
     */
    protected static function expression(string $pattern): string
    {
        return self::$expressions[$pattern] ??= Glob::toRegex(self::normalize($pattern));
    }

    /**
     * Determine if the given path matches any file watcher pattern.
     *
     * @param  array<int, string>  $patterns
     */
    public static function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if any path matches any file watcher pattern.
     *
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $patterns
     */
    public static function matchesAnyPath(array $paths, array $patterns): bool
    {
        foreach ($paths as $path) {
            if (self::matchesAny($path, $patterns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize paths and patterns for glob matching.
     */
    protected static function normalize(string $value): string
    {
        return '/' . ltrim(str_replace('\\', '/', $value), '/');
    }
}
