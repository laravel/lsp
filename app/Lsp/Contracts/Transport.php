<?php

declare(strict_types=1);

namespace App\Lsp\Contracts;

use App\Lsp\Transport\JsonRpcRequest;
use Closure;

interface Transport
{
    /**
     * Register a handler for incoming messages.
     *
     * @param  (Closure(string): void)  $handler
     */
    public function onReceive(Closure $handler): void;

    /**
     * Dispatch an incoming JSON-RPC request.
     *
     * @param  (Closure(JsonRpcRequest): void)  $dispatch
     */
    public function dispatch(JsonRpcRequest $request, Closure $dispatch): void;

    /**
     * Cancel the in-flight request with the given id.
     */
    public function cancel(int|string $id): void;

    /**
     * Start listening for incoming messages.
     */
    public function run(): void;

    /**
     * Send a message to the client.
     */
    public function send(string $message): void;
}
