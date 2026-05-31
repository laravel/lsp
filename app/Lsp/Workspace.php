<?php

declare(strict_types=1);

namespace App\Lsp;

use App\Lsp\Support\FileUri;

class Workspace
{
    /**
     * The document manager for the workspace.
     */
    public readonly DocumentManager $documents;

    /**
     * The PHP runner for the workspace.
     */
    public readonly PhpRunner $php;

    /**
     * The workspace data providers.
     */
    public readonly WorkspaceData $data;

    /**
     * The feature registry.
     */
    public readonly FeatureRegistry $features;

    /**
     * Create a new workspace instance.
     */
    public function __construct(
        public readonly string $baseUri,
        public readonly Server $server,
        public readonly WorkspaceConfiguration $config,
    ) {
        $this->documents = new DocumentManager;
        $this->php = new PhpRunner($this);
        $this->data = new WorkspaceData($this);
        $this->features = new FeatureRegistry($this);
    }

    /**
     * Get the path to the workspace root.
     */
    public function path(string $path = ''): string
    {
        return FileUri::of($this->baseUri)->path($path);
    }

    /**
     * Get the file URI to the workspace root.
     */
    public function uri(): FileUri
    {
        return FileUri::of($this->baseUri);
    }

    /**
     * Get a file target URI for a workspace-relative path.
     */
    public function target(string $relativePath, ?int $line = null): string
    {
        return (string) $this->uri()->target($relativePath, $line);
    }

    /**
     * Create a document link response for a workspace-relative path.
     *
     * @param  array<string, array<string, int>>  $range
     * @return array<string, mixed>
     */
    public function link(array $range, string $relativePath, ?int $line = null): array
    {
        return [
            'range'  => $range,
            'target' => $this->target($relativePath, $line),
        ];
    }

    /**
     * Get client file watcher registrations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fileWatchers(): array
    {
        $patterns = [];

        foreach ($this->features->watchers() as $watcher) {
            array_push($patterns, ...$watcher->patterns());
        }

        return collect(array_values(array_unique($patterns)))
            ->map(fn (string $pattern): array => [
                'globPattern' => [
                    'baseUri' => $this->baseUri,
                    'pattern' => $pattern,
                ],
                'kind'        => 7,
            ])
            ->values()
            ->all();
    }
}
