<?php

declare(strict_types=1);

namespace App\Lsp\Listeners;

use App\Lsp\Contracts\Listener;
use App\Lsp\Server;
use App\Lsp\Transport\JsonRpcRequest;

class ClearDocumentDiagnostics implements Listener
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Server $server)
    {
        //
    }

    /**
     * Handle the incoming LSP notification.
     */
    public function handle(JsonRpcRequest $request): void
    {
        $this->server->notify('textDocument/publishDiagnostics', [
            'uri' => $request->get('textDocument.uri'),
            'diagnostics' => [],
        ]);
    }
}
