<?php

declare(strict_types=1);

namespace App\Lsp\Methods;

use App\Lsp\Contracts\Method;
use App\Lsp\Server;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;

final class Shutdown implements Method
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Server $server)
    {
        //
    }

    /**
     * Handle the shutdown request.
     */
    public function handle(JsonRpcRequest $request): JsonRpcResponse
    {
        $this->server->shutdown();

        return JsonRpcResponse::result($request->id(), null);
    }
}
