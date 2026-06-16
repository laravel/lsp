<?php

declare(strict_types=1);

namespace App\Lsp\Listeners;

use App\Lsp\Contracts\Listener;
use App\Lsp\Server;
use App\Lsp\Transport\JsonRpcRequest;

class CancelRequest implements Listener
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Server $server)
    {
        //
    }

    /**
     * Handle the $/cancelRequest notification.
     */
    public function handle(JsonRpcRequest $request): void
    {
        $id = $request->get('id');

        if (is_int($id) || is_string($id)) {
            $this->server->cancel($id);
        }
    }
}
