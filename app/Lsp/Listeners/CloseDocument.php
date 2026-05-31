<?php

declare(strict_types=1);

namespace App\Lsp\Listeners;

use App\Lsp\Contracts\Listener;
use App\Lsp\DocumentManager;
use App\Lsp\Transport\JsonRpcRequest;

class CloseDocument implements Listener
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected DocumentManager $documents)
    {
        //
    }

    /**
     * Handle the textDocument/didClose notification.
     */
    public function handle(JsonRpcRequest $request): void
    {
        $this->documents->close(
            $request->get('textDocument.uri')
        );
    }
}
