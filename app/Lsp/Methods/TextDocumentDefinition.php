<?php

declare(strict_types=1);

namespace App\Lsp\Methods;

use App\Lsp\Contracts\Method;
use App\Lsp\Document;
use App\Lsp\DocumentManager;
use App\Lsp\FeatureRegistry;
use App\Lsp\Project;
use App\Lsp\Support\Position;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;

class TextDocumentDefinition implements Method
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
     * Handle the textDocument/definition request.
     */
    public function handle(JsonRpcRequest $request): JsonRpcResponse
    {
        $document = $this->documents->get(
            (string) $request->get('textDocument.uri')
        );

        if ($document === null) {
            return JsonRpcResponse::result($request->id(), []);
        }

        $position = $request->get('position', []);

        if (!is_array($position)) {
            return JsonRpcResponse::result($request->id(), []);
        }

        if (!is_int($position['line'] ?? null) || !is_int($position['character'] ?? null)) {
            return JsonRpcResponse::result($request->id(), []);
        }

        $locationLinks = [];

        foreach ($this->links($request, $document) as $link) {
            $range = $link['range'] ?? null;

            if (!is_array($range) || !Position::inRange($range, $position)) {
                continue;
            }

            $locationLinks[] = $this->locationLink($link, $range);
        }

        return JsonRpcResponse::result($request->id(), $locationLinks);
    }

    /**
     * Get document links from every registered link provider.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function links(JsonRpcRequest $request, Document $document): array
    {
        $links = [];

        foreach ($this->features->links() as $provider) {
            array_push($links, ...$provider->get($document));

            $request->cancelIfRequested();
        }

        return $links;
    }

    /**
     * Convert a document link into a definition LocationLink.
     *
     * @param  array<string, mixed>  $link
     * @param  array<string, mixed>  $originRange
     * @return array<string, mixed>
     */
    protected function locationLink(array $link, array $originRange): array
    {
        [$targetUri, $targetRange] = $this->target((string) $link['target']);

        return [
            'originSelectionRange' => $originRange,
            'targetUri'            => $targetUri,
            'targetRange'          => $targetRange,
            'targetSelectionRange' => $targetRange,
        ];
    }

    /**
     * Get the target URI and range for a document link target.
     *
     * @return array{0: string, 1: array<string, array<string, int>>}
     */
    protected function target(string $target): array
    {
        $targetUri = $target;
        $line = 0;

        if (preg_match('/^(.*)#L([1-9][0-9]*)$/', $target, $matches) === 1) {
            $targetUri = $matches[1];
            $line = ((int) $matches[2]) - 1;
        }

        return [
            $targetUri,
            [
                'start' => [
                    'line'      => $line,
                    'character' => 0,
                ],
                'end' => [
                    'line'      => $line,
                    'character' => 0,
                ],
            ],
        ];
    }
}
