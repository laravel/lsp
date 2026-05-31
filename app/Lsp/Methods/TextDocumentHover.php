<?php

declare(strict_types=1);

namespace App\Lsp\Methods;

use App\Lsp\Contracts\Method;
use App\Lsp\DocumentManager;
use App\Lsp\FeatureRegistry;
use App\Lsp\Project;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;

class TextDocumentHover implements Method
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(
        protected DocumentManager $documents,
        protected FeatureRegistry $features,
        protected Project $project,
    ) {}

    /**
     * Handle the textDocument/hover request.
     */
    public function handle(JsonRpcRequest $request): JsonRpcResponse
    {
        $document = $this->documents->get(
            (string) $request->get('textDocument.uri')
        );

        if ($document === null) {
            return JsonRpcResponse::result($request->id(), null);
        }

        $position = $request->get('position', []);

        if (!is_array($position)) {
            return JsonRpcResponse::result($request->id(), null);
        }

        foreach ($this->features->hovers() as $provider) {
            $hover = $provider->get($document, $position);

            if ($hover !== null) {
                return JsonRpcResponse::result($request->id(), $hover);
            }
        }

        return JsonRpcResponse::result($request->id(), null);
    }
}
