<?php

declare(strict_types=1);

namespace App\Lsp\Contracts;

use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Workspace;

interface Listener
{
    /**
     * Handle the incoming LSP notification.
     */
    public function handle(JsonRpcRequest $request): void;
}
