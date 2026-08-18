<?php

declare(strict_types=1);

namespace App\Lsp\Transport;

class JsonRpcResponse
{
    /**
     * Create a new JSON-RPC response instance.
     *
     * @param  array<string, mixed>  $content
     */
    public function __construct(protected array $content = [])
    {
        //
    }

    /**
     * Create a successful response.
     *
     * @param  array<mixed>|null  $result
     */
    public static function result(int|string $id, ?array $result): static
    {
        return new static([
            'id'     => $id,
            'result' => $result,
        ]);
    }

    /**
     * Create a notification response.
     *
     * @param  array<string, mixed>  $params
     */
    public static function notification(string $method, array $params): static
    {
        return new static([
            'method' => $method,
            'params' => $params === [] ? (object) [] : $params,
        ]);
    }

    /**
     * Create an error response.
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function error(string|int|null $id, int $code, string $message, ?array $data = null): static
    {
        $error = [
            'code'    => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return new static([
            'id'    => $id,
            'error' => $error,
        ]);
    }

    /**
     * Get a copy of the response with its result passed through the callback.
     */
    public function mapResult(callable $callback): static
    {
        if (!array_key_exists('result', $this->content)) {
            return $this;
        }

        return new static([...$this->content, 'result' => $callback($this->content['result'])]);
    }

    /**
     * Get the response as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => '2.0',
            ...$this->content,
        ];
    }

    /**
     * Get the response as a JSON string.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
