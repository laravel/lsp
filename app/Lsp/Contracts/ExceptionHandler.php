<?php

namespace App\Lsp\Contracts;

use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;
use Throwable;

interface ExceptionHandler
{
    /**
     * Report the given exception.
     */
    public function report(Throwable $e): void;

    /**
     * Render the given exception.
     */
    public function render(JsonRpcRequest $request, Throwable $e): JsonRpcResponse;
}
