<?php

declare(strict_types=1);

namespace App\Lsp\Methods;

use App\Lsp\Contracts\ExceptionHandler;
use App\Lsp\Contracts\Method;
use App\Lsp\DocumentManager;
use App\Lsp\PhpCommandDetector;
use App\Lsp\Project;
use App\Lsp\ProjectIndex;
use App\Lsp\ScriptRunner;
use App\Lsp\Support\FileUri;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;
use Illuminate\Container\Container;

final class Initialize implements Method
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Container $container)
    {
        //
    }

    /**
     * Handle the incoming LSP request.
     */
    public function handle(JsonRpcRequest $request): JsonRpcResponse
    {
        $this->container->singleton(Project::class);

        $this->container->instance(Project::class, new Project(
            $uri = FileUri::of($request->get('rootUri')),
            $request->array('initializationOptions'),
            new ProjectIndex($this->container),
            new ScriptRunner($uri->path(), $this->phpCommand($request)),
        ));

        return JsonRpcResponse::result($request->id(), []);
    }

    /**
     * Resolve the php command.
     *
     * @return array<int, string>
     */
    protected function phpCommand(JsonRpcRequest $request): array
    {
        if ($command = $request->array('initializationOptions.phpCommand')) {
            return $command;
        }

        return (new PhpCommandDetector(
            FileUri::of($request->get('rootUri'))->path(),
            (string) $request->string('initializationOptions.phpEnvironment', 'auto'),
            $this->container[ExceptionHandler::class],
        ))->detect();
    }
}
