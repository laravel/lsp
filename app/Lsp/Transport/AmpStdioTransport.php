<?php

declare(strict_types=1);

namespace App\Lsp\Transport;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\DeferredCancellation;
use App\Lsp\Contracts\Transport;
use Closure;

use function Amp\async;

class AmpStdioTransport implements Transport
{
    /**
     * The message handler callback.
     *
     * @var (Closure(string): void)|null
     */
    protected ?Closure $handler = null;

    /**
     * The non-blocking STDIN stream.
     */
    protected ReadableResourceStream $stdin;

    /**
     * The non-blocking STDOUT stream.
     */
    protected WritableResourceStream $stdout;

    /**
     * The buffer of incoming bytes pending frame extraction.
     */
    protected string $buffer = '';

    /**
     * The cancellation sources of in-flight asynchronous requests.
     *
     * @var array<int|string, DeferredCancellation>
     */
    protected array $cancellations = [];

    /**
     * Instantiate a new class instance.
     */
    public function __construct()
    {
        $this->stdin = new ReadableResourceStream(STDIN);
        $this->stdout = new WritableResourceStream(STDOUT);
    }

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
        if ($request->isNotification()) {
            async(fn () => $dispatch($request));

            return;
        }

        $deferred = new DeferredCancellation;

        $this->cancellations[$request->id()] = $deferred;
        $request->setCancellation($deferred->getCancellation());

        async(function () use ($request, $dispatch): void {
            try {
                $dispatch($request);
            } finally {
                unset($this->cancellations[$request->id()]);
            }
        });
    }

    /**
     * Cancel the in-flight request with the given id.
     */
    public function cancel(int|string $id): void
    {
        if (isset($this->cancellations[$id])) {
            $this->cancellations[$id]->cancel();
        }
    }

    /**
     * Send a message to the client with LSP Content-Length framing.
     */
    public function send(string $message): void
    {
        $length = strlen($message);

        $this->stdout->write("Content-Length: {$length}\r\n\r\n{$message}");
    }

    /**
     * Start the main event loop, reading from STDIN.
     */
    public function run(): void
    {
        while (($chunk = $this->stdin->read()) !== null) {
            $this->buffer .= $chunk;

            while (($message = $this->extractMessage()) !== null) {
                if (is_callable($this->handler)) {
                    ($this->handler)($message);
                }
            }
        }
    }

    /**
     * Extract the next complete Content-Length framed message from the buffer.
     */
    protected function extractMessage(): ?string
    {
        $separator = strpos($this->buffer, "\r\n\r\n");

        if ($separator === false) {
            return null;
        }

        $length = $this->parseContentLength(substr($this->buffer, 0, $separator));

        if ($length === null) {
            $this->buffer = substr($this->buffer, $separator + 4);

            return $this->extractMessage();
        }

        $offset = $separator + 4;

        if (strlen($this->buffer) < $offset + $length) {
            return null;
        }

        $body = substr($this->buffer, $offset, $length);

        $this->buffer = substr($this->buffer, $offset + $length);

        return $body;
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
}
