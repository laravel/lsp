<?php

declare(strict_types=1);

namespace App\Lsp\Listeners;

use App\Lsp\Contracts\Listener;
use App\Lsp\Document;
use App\Lsp\DocumentManager;
use App\Lsp\FeatureRegistry;
use App\Lsp\Server;
use App\Lsp\Transport\JsonRpcRequest;

class PublishDiagnostics implements Listener
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(
        protected Server $server,
        protected DocumentManager $documents,
        protected FeatureRegistry $features,
    ) {}

    /**
     * Handle the incoming LSP notification.
     */
    public function handle(JsonRpcRequest $request): void
    {
        $document = $this->documents->get(
            $request->get('textDocument.uri')
        );

        if ($document === null) {
            return;
        }

        $this->publish($document);
    }

    /**
     * Publish diagnostics for the given document.
     */
    public function publish(Document $document): void
    {
        $diagnostics = [];

        foreach ($this->features->diagnostics() as $provider) {
            array_push($diagnostics, ...$provider->get($document));
        }

        $this->server->notify('textDocument/publishDiagnostics', [
            'uri' => $document->uri,
            'diagnostics' => $diagnostics,
        ]);
    }
}
