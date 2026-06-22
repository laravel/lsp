<?php

declare(strict_types=1);

namespace App\Lsp\Transport;

use App\Lsp\Contracts\Transport;
use Closure;

class StdioTransport implements Transport
{
    /**
     * The message handler callback.
     *
     * @var (Closure(string): void)|null
     */
    protected ?Closure $handler = null;

    /**
     * Register a handler for incoming messages.
     */
    public function onReceive(Closure $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * Dispatch an incoming JSON-RPC request.
     *
     * @param  (Closure(JsonRpcRequest): void)  $dispatch
     */
    public function dispatch(JsonRpcRequest $request, Closure $dispatch): void
    {
        $dispatch($request);
    }

    /**
     * Cancel the in-flight request with the given id.
     */
    public function cancel(int|string $id): void
    {
        //
    }

    /**
     * Send a message to the client with LSP Content-Length framing.
     */
    public function send(string $message): void
    {
        $length = strlen($message);

        fwrite(STDOUT, "Content-Length: {$length}\r\n\r\n{$message}");
    }

    /**
     * Start the main event loop, reading from STDIN.
     */
    public function run(): void
    {
        while (!feof(STDIN)) {
            $headers = $this->readHeaders();

            if ($headers === null) {
                continue;
            }

            $contentLength = $this->parseContentLength($headers);

            if ($contentLength === null) {
                continue;
            }

            $body = $this->readBody($contentLength);

            if ($body === null) {
                continue;
            }

            if (is_callable($this->handler)) {
                ($this->handler)($body);
            }
        }
    }

    /**
     * Read LSP headers from STDIN until the blank line separator.
     */
    protected function readHeaders(): ?string
    {
        $headers = '';

        while (true) {
            $line = fgets(STDIN);

            if ($line === false) {
                return null;
            }

            $headers .= $line;

            if ($line === "\r\n") {
                return $headers;
            }
        }
    }

    /**
     * Extract the Content-Length value from the raw headers.
     */
    protected function parseContentLength(string $headers): ?int
    {
        if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Read the message body of the given length from STDIN.
     */
    protected function readBody(int $length): ?string
    {
        $body = '';

        while (strlen($body) < $length) {
            $chunk = fread(STDIN, $length - strlen($body));

            if ($chunk === false || $chunk === '') {
                return null;
            }

            $body .= $chunk;
        }

        return $body;
    }
}
