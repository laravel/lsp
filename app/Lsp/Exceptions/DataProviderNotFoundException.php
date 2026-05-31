<?php

namespace App\Lsp\Exceptions;

use App\Lsp\Contracts\Responsable;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;
use Exception;

class DataProviderNotFoundException extends Exception implements Responsable
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(public readonly string $provider)
    {
        parent::__construct('Data provider not found.');
    }

    /**
     * Create a JSON-RPC response that represents the object.
     */
    public function toResponse(JsonRpcRequest $request): JsonRpcResponse
    {
        return JsonRpcResponse::error($request->id(), -32602, $this->getMessage(), ['provider' => $this->provider]);
    }
}
