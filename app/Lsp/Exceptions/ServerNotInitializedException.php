<?php

namespace App\Lsp\Exceptions;

use App\Lsp\Contracts\Responsable;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;
use Exception;

class ServerNotInitializedException extends Exception implements Responsable
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct()
    {
        parent::__construct('Server not initialized.');
    }

    /**
     * Create a JSON-RPC response that represents the object.
     */
    public function toResponse(JsonRpcRequest $request): JsonRpcResponse
    {
        return JsonRpcResponse::error($request->id(), -32002, $this->getMessage());
    }
}
