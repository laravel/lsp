<?php

declare(strict_types=1);

namespace App\Lsp\Listeners;

use App\Lsp\Contracts\FileWatcher;
use App\Lsp\Contracts\Listener;
use App\Lsp\FeatureRegistry;
use App\Lsp\Project;
use App\Lsp\Server;
use App\Lsp\Transport\JsonRpcRequest;

class RegisterFileWatchers implements Listener
{
        /**
     * Instantiate a new class instance.
     */
    public function __construct(
        protected Server $server,
        protected FeatureRegistry $features,
        protected Project $project,
    ) {}

    /**
     * Handle the incoming LSP notification.
     */
    public function handle(JsonRpcRequest $request): void
    {
        $watchers = $this->features->watchers();

        $this->server->send('client/registerCapability', [
            'registrations' => [
                [
                    'id' => 'file-watching',
                    'method' => 'workspace/didChangeWatchedFiles',
                    'registerOptions' => [
                        'watchers' => array_map(fn (string $pattern) => [
                            'globPattern' => [
                                'baseUri' => $this->project->uri,
                                'pattern' => $pattern,
                            ],
                            'kind' => 7,
                        ], $this->patterns($watchers)),
                    ],
                ],
            ],
        ]);

        foreach ($watchers as $watcher) {
            $watcher->initialize();
        }
    }

    /**
     * Collect watcher patterns.
     *
     * @param  array<int, FileWatcher>  $watchers
     * @return array<int, string>
     */
    protected function patterns(array $watchers): array
    {
        $patterns = [];

        foreach ($watchers as $watcher) {
            array_push($patterns, ...$watcher->patterns());
        }

        return array_values(array_unique($patterns));
    }
}
