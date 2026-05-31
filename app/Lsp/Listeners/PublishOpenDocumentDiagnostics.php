<?php

declare(strict_types=1);

namespace App\Lsp\Listeners;

use App\Lsp\Contracts\Listener;
use App\Lsp\DocumentManager;
use App\Lsp\Transport\JsonRpcRequest;

class PublishOpenDocumentDiagnostics implements Listener
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(
        protected DocumentManager $documents,
        protected PublishDiagnostics $publisher,
    ) {}

    /**
     * Handle the workspace/didChangeWatchedFiles notification.
     */
    public function handle(JsonRpcRequest $request): void
    {
        foreach ($this->documents->all() as $document) {
            $this->publisher->publish($document);
        }
    }
}
