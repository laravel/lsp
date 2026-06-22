<?php

declare(strict_types=1);

namespace App\Lsp;

use App\Lsp\Contracts\ExceptionHandler;
use App\Lsp\Contracts\Listener;
use App\Lsp\Contracts\Method;
use App\Lsp\Contracts\Transport;
use App\Lsp\Exceptions\Handler;
use App\Lsp\Exceptions\MethodNotFoundException;
use App\Lsp\Exceptions\ParseException;
use App\Lsp\Exceptions\ServerNotInitializedException;
use App\Lsp\Listeners\CancelRequest;
use App\Lsp\Listeners\ClearDocumentDiagnostics;
use App\Lsp\Listeners\CloseDocument;
use App\Lsp\Listeners\NotifyFileWatchers;
use App\Lsp\Listeners\OpenDocument;
use App\Lsp\Listeners\PublishDiagnostics;
use App\Lsp\Listeners\PublishOpenDocumentDiagnostics;
use App\Lsp\Listeners\RegisterFileWatchers;
use App\Lsp\Listeners\UpdateDocument;
use App\Lsp\Methods\Initialize;
use App\Lsp\Methods\LaravelData;
use App\Lsp\Methods\TextDocumentCodeAction;
use App\Lsp\Methods\TextDocumentCompletion;
use App\Lsp\Methods\TextDocumentDefinition;
use App\Lsp\Methods\TextDocumentDocumentLink;
use App\Lsp\Methods\TextDocumentHover;
use App\Lsp\Transport\AmpStdioTransport;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;
use App\Lsp\Transport\StdioTransport;
use Illuminate\Container\Container;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class Server
{
    /**
     * The registered request handlers.
     *
     * @var array<string, class-string<Method>>
     */
    protected array $handlers = [
        'initialize'                => Initialize::class,
        'laravel/data'              => LaravelData::class,
        'textDocument/codeAction'   => TextDocumentCodeAction::class,
        'textDocument/completion'   => TextDocumentCompletion::class,
        'textDocument/definition'   => TextDocumentDefinition::class,
        'textDocument/documentLink' => TextDocumentDocumentLink::class,
        'textDocument/hover'        => TextDocumentHover::class,
    ];

    /**
     * The registered notification listeners.
     *
     * @var array<string, array<int, class-string<Listener>>>
     */
    protected array $listeners = [
        '$/cancelRequest'                 => [CancelRequest::class],
        'initialized'                     => [RegisterFileWatchers::class],
        'textDocument/didOpen'            => [OpenDocument::class, PublishDiagnostics::class],
        'textDocument/didChange'          => [UpdateDocument::class, PublishDiagnostics::class],
        'textDocument/didClose'           => [CloseDocument::class, ClearDocumentDiagnostics::class],
        'workspace/didChangeWatchedFiles' => [NotifyFileWatchers::class, PublishOpenDocumentDiagnostics::class],
    ];

    /**
     * Store the last sent request id.
     */
    protected int $lastRequestId = 0;

    /**
     * Instantiate a new class instance.
     */
    public function __construct(
        protected Transport $transport,
        protected LoggerInterface $logger = new NullLogger,
        protected Container $container = new Container,
    ) {
        $this->registerBaseBindings();
    }

    /**
     * Create a new stdio server instance.
     */
    public static function stdio(): static
    {
        return new self(
            new StdioTransport,
            new Logger('Laravel LSP', [new StreamHandler('php://stderr')]),
        );
    }

    /**
     * Create a new async stdio server instance.
     */
    public static function asyncStdio(): static
    {
        return new self(
            new AmpStdioTransport,
            new Logger('Laravel LSP', [new StreamHandler('php://stderr')]),
        );
    }

    /**
     * Start the server.
     */
    public function start(): void
    {
        $this->transport->onReceive($this->handle(...));
        $this->transport->run();
    }

    /**
     * Handle the incoming request.
     */
    protected function handle(string $message): void
    {
        try {
            $request = $this->decode($message);
        } catch (Throwable $e) {
            $this->container[ExceptionHandler::class]->report($e);

            $this->respond(JsonRpcResponse::error(null, -32700, $e->getMessage(), [
                'message' => $message,
            ]));

            return;
        }

        if ($request === null) {
            return;
        }

        $this->transport->dispatch($request, $this->dispatch(...));
    }

    /**
     * Respond to the current request.
     */
    protected function respond(JsonRpcResponse $response): void
    {
        $this->transport->send($response->toJson());
    }

    /**
     * Send a request to the client.
     */
    public function send(string $method, ?array $params = null)
    {
        $data = [
            'jsonrpc' => '2.0',
            'id'      => $this->lastRequestId++,
            'method'  => $method,
        ];

        if (!is_null($params)) {
            $data['params'] = $params;
        }

        $this->transport->send(json_encode($data));
    }

    /**
     * Send a notification to the client.
     */
    public function notify(string $method, ?array $params = null)
    {
        $data = [
            'jsonrpc' => '2.0',
            'method'  => $method,
        ];

        if (!is_null($params)) {
            $data['params'] = $params;
        }

        $this->transport->send(json_encode($data));
    }

    /**
     * Decode the request.
     */
    protected function decode(string $message): ?JsonRpcRequest
    {
        $payload = json_decode($message, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ParseException;
        }

        if ($this->isResponse($payload)) {
            return null;
        }

        return JsonRpcRequest::from($payload);
    }

    /**
     * Determine if the payload is a JSON-RPC response to a server request.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function isResponse(array $payload): bool
    {
        return !isset($payload['method'])
            && array_key_exists('id', $payload)
            && (array_key_exists('result', $payload) || array_key_exists('error', $payload));
    }

    /**
     * Dispatch the request.
     */
    public function dispatch(JsonRpcRequest $request): void
    {
        if ($request->isNotification()) {
            $this->dispatchNotification($request);

            return;
        }

        $this->dispatchRequest($request);
    }

    /**
     * Dispatch the notification.
     */
    public function dispatchNotification(JsonRpcRequest $request): void
    {
        $this->handleNotification($request);
    }

    /**
     * Dispatch the request.
     */
    public function dispatchRequest(JsonRpcRequest $request): void
    {
        $this->respond($this->handleRequest($request));
    }

    /**
     * Handle the notification.
     */
    protected function handleNotification(JsonRpcRequest $request): void
    {
        foreach ($this->listeners($request->method()) as $listener) {
            try {
                $listener->handle($request);
            } catch (Throwable $e) {
                $this->container[ExceptionHandler::class]->report($e);
            }
        }
    }

    /**
     * Handle the request and render any exception to a JSON-RPC response.
     */
    protected function handleRequest(JsonRpcRequest $request): JsonRpcResponse
    {
        try {
            return $this->handler($request->method())->handle($request);
        } catch (Throwable $e) {
            $this->container[ExceptionHandler::class]->report($e);

            return $this->container[ExceptionHandler::class]->render($request, $e);
        }
    }

    /**
     * Cancel the in-flight request with the given id.
     */
    public function cancel(int|string $id): void
    {
        $this->transport->cancel($id);
    }

    /**
     * Retrieve the request handler.
     */
    protected function handler(string $method): Method
    {
        $class = $this->handlers[$method]
            ?? throw new MethodNotFoundException($method);

        return $this->container->make($class);
    }

    /**
     * Get listeners of the given notification.
     *
     * @return array<int, Listener>
     */
    protected function listeners(string $notification): array
    {
        return array_map(
            fn (string $class) => $this->container->make($class),
            $this->listeners[$notification] ?? [],
        );
    }

    /**
     * Register base container bindings.
     */
    protected function registerBaseBindings(): void
    {
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Server::class, $this);
        $this->container->instance(Transport::class, $this->transport);
        $this->container->instance(LoggerInterface::class, $this->logger);

        $this->container->singletonIf(DocumentManager::class);
        $this->container->singletonIf(ExceptionHandler::class, Handler::class);

        $this->container->singletonIf(Project::class, fn () => throw new ServerNotInitializedException);
        $this->container->singletonIf(FeatureRegistry::class);
    }
}
