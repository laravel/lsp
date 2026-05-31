<?php

declare(strict_types=1);

namespace App\Lsp\Watchers;

use App\Lsp\Contracts\FileWatcher;
use App\Lsp\Project;

class DataProviderWatcher implements FileWatcher
{
    /**
     * Create a new data provider watcher instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Initialize the data provider watcher.
     */
    public function initialize(): void
    {
        //
    }

    /**
     * Get data provider watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return $this->project->index->patterns();
    }

    /**
     * Handle changed workspace-relative paths.
     *
     * @param  array<int, string>  $changes
     */
    public function onFileChange(array $changes): void
    {
        $this->project->index->invalidate($changes);
    }
}
