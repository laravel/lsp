<?php

declare(strict_types=1);

namespace App\Lsp\Listeners;

use App\Lsp\Contracts\Listener;
use App\Lsp\FeatureRegistry;
use App\Lsp\Project;
use App\Lsp\Support\Pattern;
use App\Lsp\Support\FileUri;
use App\Lsp\Transport\JsonRpcRequest;

class NotifyFileWatchers implements Listener
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(
        protected FeatureRegistry $features,
        protected Project $project,
    ) {}

    /**
     * Handle the workspace/didChangeWatchedFiles notification.
     */
    public function handle(JsonRpcRequest $request): void
    {
        $paths = $this->paths($request);

        if ($paths === []) {
            return;
        }

        foreach ($this->features->watchers() as $watcher) {
            if (Pattern::matchesAnyPath($paths, $watcher->patterns())) {
                $watcher->onFileChange($paths);
            }
        }
    }

    /**
     * Get changed workspace-relative paths.
     *
     * @return array<int, string>
     */
    protected function paths(JsonRpcRequest $request): array
    {
        return $request->collect('changes')
            ->pluck('uri')
            ->filter(fn (mixed $uri): bool => is_string($uri))
            ->map(fn (string $uri): string => $this->relativePath(FileUri::of($uri)->path()))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get a path relative to the current URI.
     */
    public function relativePath(string $path): string
    {
        $basePath = $this->project->path();

        if (!str_contains($path, $basePath)) {
            return $path;
        }

        return ltrim(str_replace($basePath, '', realpath($path) ?: $path), DIRECTORY_SEPARATOR);
    }
}
