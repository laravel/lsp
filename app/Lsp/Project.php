<?php

declare(strict_types=1);

namespace App\Lsp;

use App\Lsp\Support\FileUri;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\InteractsWithData;

final class Project
{
    use InteractsWithData;

    /**
     * Create a new project instance.
     */
    public function __construct(
        public readonly FileUri $uri,
        public readonly array $init,
        public readonly ProjectIndex $index,
        public readonly ScriptRunner $scripts,
    ) {}

    /**
     * Get the configured PHP environment.
     */
    public function phpEnvironment(): string
    {
        return (string) $this->data('phpEnvironment', 'auto');
    }

    /**
     * Get the configured LSP process memory limit.
     */
    public function memoryLimit(): string
    {
        $value = $this->data('memoryLimit', '512M');

        if (!is_string($value) && !is_int($value)) {
            return '512M';
        }

        $limit = trim((string) $value);

        return preg_match('/^(-1|\d+[KMG]?)$/i', $limit) === 1
            ? $limit
            : '512M';
    }

    /**
     * Apply the configured memory limit to the current process.
     */
    public function applyMemoryLimit(): string
    {
        $limit = $this->memoryLimit();

        ini_set('memory_limit', $limit);

        return $limit;
    }

    /**
     * Get the file URI to the workspace root.
     */
    public function uri(): FileUri
    {
        return $this->uri;
    }

    /**
     * Get the base path of the project.
     */
    public function path(string $path = ''): string
    {
        return $this->uri->path($path);
    }

    /**
     * Join the given paths.
     */
    public function joinPaths(string ...$paths): FileUri
    {
        return $this->uri->joinPaths(...$paths);
    }

    /**
     * Create a URI that targets a specific file and line.
     */
    public function target(string $path, ?int $line = null): FileUri
    {
        return $this->uri->target($path, $line);
    }

    /**
     * Create a document link response for a workspace-relative path.
     *
     * @param  array<string, array<string, int>>  $range
     * @return array<string, mixed>
     */
    public function link(array $range, string $path, ?int $line = null): array
    {
        return [
            'range'  => $range,
            'target' => $this->target($path, $line),
        ];
    }

    /**
     * Get all stored init options.
     *
     * @param  array|mixed|null  $keys
     * @return array<string, mixed>
     */
    public function all($keys = null): array
    {
        if (!$keys) {
            return $this->init;
        }

        $results = [];

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            Arr::set($results, $key, Arr::get($this->init, $key));
        }

        return $results;
    }

    /**
     * Retrieve data from the stored init options.
     */
    protected function data($key = null, $default = null): mixed
    {
        if ($key === null) {
            return $this->init;
        }

        return Arr::get($this->init, $key, $default);
    }
}
