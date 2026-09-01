<?php

declare(strict_types=1);

namespace App\Lsp\Methods;

use App\Lsp\Contracts\Method;
use App\Lsp\Contracts\ReferenceProvider;
use App\Lsp\Document;
use App\Lsp\DocumentManager;
use App\Lsp\FeatureRegistry;
use App\Lsp\Project;
use App\Lsp\Support\FileUri;
use App\Lsp\Support\WorkspaceFiles;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;

class TextDocumentReferences implements Method
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
     * Handle the textDocument/references request.
     */
    public function handle(JsonRpcRequest $request): JsonRpcResponse
    {
        $document = $this->documents->get(
            (string) $request->get('textDocument.uri')
        );

        $position = $request->array('position');

        if ($document === null || !$this->project->boolean('referencesProvider', true)) {
            return JsonRpcResponse::result($request->id(), []);
        }

        [$provider, $symbol] = $this->symbolAt($document, $position);

        if ($provider === null || $symbol === null) {
            return JsonRpcResponse::result($request->id(), []);
        }

        return JsonRpcResponse::result(
            $request->id(),
            $this->locations($provider, $symbol, $request),
        );
    }

    /**
     * Resolve the provider and symbol under the cursor.
     *
     * @param  array<string, mixed>  $position
     * @return array{0: ReferenceProvider|null, 1: string|null}
     */
    protected function symbolAt(Document $document, array $position): array
    {
        foreach ($this->features->references() as $provider) {
            $symbol = $provider->symbolAt($document, $position);

            if ($symbol !== null && $symbol !== '') {
                return [$provider, $symbol];
            }
        }

        return [null, null];
    }

    /**
     * Collect every workspace location referencing the symbol.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function locations(
        ReferenceProvider $provider,
        string $symbol,
        JsonRpcRequest $request,
    ): array {
        $locations = [];

        foreach ((new WorkspaceFiles($this->project))->containing($symbol) as $path => $contents) {
            $uri = (string) FileUri::fromPath($path);

            // Prefer the editor's copy so unsaved edits are not searched stale.
            $scanned = $this->documents->get($uri) ?? new Document($uri, $contents);

            foreach ($provider->ranges($scanned, $symbol) as $range) {
                $locations[] = ['uri' => $uri, 'range' => $range];
            }

            $request->cancelIfRequested();
        }

        return $locations;
    }
}
