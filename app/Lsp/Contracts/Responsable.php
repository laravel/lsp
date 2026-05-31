<?php

namespace App\Lsp\Contracts;

use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;

interface Responsable
{
    /**
     * Create a JSON-RPC response that represents the object.
     */
    public function toResponse(JsonRpcRequest $request): JsonRpcResponse;
}
