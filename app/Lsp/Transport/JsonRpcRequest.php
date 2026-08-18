<?php

declare(strict_types=1);

namespace App\Lsp\Transport;

use Amp\Cancellation;
use App\Lsp\Exceptions\RequestCancelledException;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\InteractsWithData;

use function Amp\delay;

class JsonRpcRequest
{
    use InteractsWithData;

    /**
     * The cancellation source for the request.
     */
    protected ?Cancellation $cancellation = null;

    /**
     * Create a new JSON-RPC request instance.
     *
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public int|string $id,
        protected string $method,
        protected array $params,
        protected bool $notification = false,
    ) {}

    /**
     * Create a new request instance from the given JSON-RPC payload.
     *
     * @param  array{id?: mixed, jsonrpc?: mixed, method?: mixed, params?: array<string, mixed>}  $jsonRequest
     */
    public static function from(array $jsonRequest): static
    {
        if (!isset($jsonRequest['jsonrpc']) || $jsonRequest['jsonrpc'] !== '2.0') {
            throw new \InvalidArgumentException('Invalid Request: The [jsonrpc] member must be exactly [2.0].');
        }

        if (!isset($jsonRequest['method']) || !is_string($jsonRequest['method'])) {
            throw new \InvalidArgumentException('Invalid Request: The [method] member is required and must be a string.');
        }

        $notification = !array_key_exists('id', $jsonRequest);

        if (!$notification && !is_int($jsonRequest['id']) && !is_string($jsonRequest['id'])) {
            throw new \InvalidArgumentException('Invalid Request: The [id] member must be a string or number.');
        }

        return new static(
            id: $notification ? 0 : $jsonRequest['id'],
            method: $jsonRequest['method'],
            params: $jsonRequest['params'] ?? [],
            notification: $notification,
        );
    }

    /**
     * Get a copy of the request with the given parameters.
     *
     * @param  array<string, mixed>  $params
     */
    public function withParams(array $params): static
    {
        $request = clone $this;

        $request->params = $params;

        return $request;
    }

    /**
     * Get the request ID.
     */
    public function id(): int|string
    {
        return $this->id;
    }

    /**
     * Get the method name.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Determine if the message is a notification.
     */
    public function isNotification(): bool
    {
        return $this->notification;
    }

    /**
     * Set the cancellation source for the request.
     */
    public function setCancellation(Cancellation $cancellation): void
    {
        $this->cancellation = $cancellation;
    }

    /**
     * Throw if the request has been cancelled.
     *
     * @throws RequestCancelledException
     */
    public function cancelIfRequested(): void
    {
        if ($this->cancellation === null) {
            return;
        }

        // Yield to the event loop so a pending $/cancelRequest can be read.
        delay(0);

        if ($this->cancellation->isRequested()) {
            throw new RequestCancelledException;
        }
    }

    /**
     * Get all of the parameters from the request.
     *
     * @param  array|mixed|null  $keys
     * @return array<string, mixed>
     */
    public function all($keys = null): array
    {
        if (!$keys) {
            return $this->params;
        }

        $results = [];

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            Arr::set($results, $key, Arr::get($this->params, $key));
        }

        return $results;
    }

    /**
     * Get a parameter value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->params, $key, $default);
    }

    /**
     * Retrieve data from the request parameters.
     */
    protected function data($key = null, $default = null): mixed
    {
        if ($key === null) {
            return $this->params;
        }

        return $this->get($key, $default);
    }
}
