<?php

declare(strict_types=1);

namespace App\Lsp\Exceptions;

use App\Lsp\Contracts\ExceptionHandler;
use App\Lsp\Contracts\Responsable;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;
use Psr\Log\LoggerInterface;
use Throwable;

class Handler implements ExceptionHandler
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected LoggerInterface $logger)
    {
        //
    }

    /**
     * Report the given exception.
     */
    public function report(Throwable $e): void
    {
        $this->logger->error($e->getMessage(), ['exception' => $e]);
    }

    /**
     * Render the given exception.
     */
    public function render(JsonRpcRequest $request, Throwable $e): JsonRpcResponse
    {
        return $e instanceof Responsable
            ? $e->toResponse($request)
            : JsonRpcResponse::error($request->id(), -32603, $e->getMessage());
    }
}
