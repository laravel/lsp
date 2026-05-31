<?php

declare(strict_types=1);

namespace App\Lsp\Listeners;

use App\Lsp\Contracts\Listener;
use App\Lsp\DocumentManager;
use App\Lsp\Transport\JsonRpcRequest;

class OpenDocument implements Listener
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected DocumentManager $documents)
    {
        //
    }

    /**
     * Handle the textDocument/didOpen notification.
     */
    public function handle(JsonRpcRequest $request): void
    {
        $this->documents->open(
            $request->get('textDocument.uri'),
            $request->get('textDocument.text'),
        );
    }
}
