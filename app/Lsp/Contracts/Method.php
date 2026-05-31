<?php

declare(strict_types=1);

namespace App\Lsp\Contracts;

use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;
use App\Lsp\Workspace;

interface Method
{
    /**
     * Handle the incoming LSP request.
     */
    public function handle(JsonRpcRequest $request): JsonRpcResponse;
}
