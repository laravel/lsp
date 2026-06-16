<?php

declare(strict_types=1);

namespace App\Lsp\Methods;

use App\Lsp\Contracts\Method;
use App\Lsp\DocumentManager;
use App\Lsp\FeatureRegistry;
use App\Lsp\Project;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;

class TextDocumentDocumentLink implements Method
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
     * Handle the textDocument/documentLink request.
     */
    public function handle(JsonRpcRequest $request): JsonRpcResponse
    {
        $document = $this->documents->get(
            (string) $request->get('textDocument.uri')
        );

        if ($document === null) {
            return JsonRpcResponse::result($request->id(), []);
        }

        $links = [];

        foreach ($this->features->links() as $provider) {
            array_push($links, ...$provider->get($document));

            $request->cancelIfRequested();
        }

        return JsonRpcResponse::result($request->id(), $links);
    }
}
