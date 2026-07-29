<?php

declare(strict_types=1);

namespace App\Lsp\Exceptions;

use App\Lsp\Contracts\Responsable;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;
use Exception;

class InvalidRequestException extends Exception implements Responsable
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(string $message = 'Invalid request.')
    {
        parent::__construct($message);
    }

    /**
     * Create a JSON-RPC response that represents the object.
     */
    public function toResponse(JsonRpcRequest $request): JsonRpcResponse
    {
        return JsonRpcResponse::error($request->id(), -32600, $this->getMessage());
    }
}
