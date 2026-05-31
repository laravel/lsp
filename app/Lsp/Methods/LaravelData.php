<?php

declare(strict_types=1);

namespace App\Lsp\Methods;

use App\Lsp\Contracts\Method;
use App\Lsp\Project;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;
use Illuminate\Contracts\Support\Arrayable;

class LaravelData implements Method
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Handle the laravel/data request.
     */
    public function handle(JsonRpcRequest $request): JsonRpcResponse
    {
        if (!$name = $request->get('name')) {
            return JsonRpcResponse::error(
                $request->id(),
                -32602,
                'Invalid params: The [name] parameter is required.',
            );
        }

        $data = $this->project->index->get($name);

        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

        return JsonRpcResponse::result($request->id(), (array) $data);
    }
}
