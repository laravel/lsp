<?php

declare(strict_types=1);

namespace App\Lsp\Listeners;

use App\Lsp\Contracts\Listener;
use App\Lsp\DocumentManager;
use App\Lsp\Transport\JsonRpcRequest;

class UpdateDocument implements Listener
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected DocumentManager $documents)
    {
        //
    }

    /**
     * Handle the textDocument/didChange notification.
     */
    public function handle(JsonRpcRequest $request): void
    {
        $content = $request->collect('contentChanges');

        if ($content->isEmpty()) {
            return;
        }

        $this->documents->update(
            $request->get('textDocument.uri'),
            $content->last()['text'],
        );
    }
}
