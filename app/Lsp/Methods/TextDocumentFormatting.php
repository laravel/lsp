<?php

declare(strict_types=1);

namespace App\Lsp\Methods;

use App\Lsp\Contracts\Method;
use App\Lsp\DocumentManager;
use App\Lsp\Project;
use App\Lsp\Support\FileUri;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;

class TextDocumentFormatting implements Method
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(
        protected DocumentManager $documents,
        protected Project $project,
    ) {}

    /**
     * Handle the textDocument/formatting request.
     */
    public function handle(JsonRpcRequest $request): JsonRpcResponse
    {
        if (!$this->project->formatsDocuments()) {
            return JsonRpcResponse::result($request->id(), null);
        }

        $uri = (string) $request->get('textDocument.uri');

        $document = $this->documents->get($uri);

        if ($document === null) {
            return JsonRpcResponse::result($request->id(), null);
        }

        // Formatting spawns Pint, so give a cancellation that arrived while
        // the document was still being typed a chance to land first.
        $request->cancelIfRequested();

        $formatted = $this->project->pint->format(
            FileUri::of($uri)->path(),
            $document->content,
        );

        if ($formatted === null || $formatted === $document->content) {
            return JsonRpcResponse::result($request->id(), null);
        }

        return JsonRpcResponse::result($request->id(), [
            [
                'range'   => static::range($document->content),
                'newText' => $formatted,
            ],
        ]);
    }

    /**
     * Get the LSP range spanning the entirety of the given contents.
     *
     * @return array<string, array<string, int>>
     */
    public static function range(string $contents): array
    {
        $lines = explode("\n", $contents);
        $last = array_key_last($lines);

        return [
            'start' => ['line' => 0, 'character' => 0],
            'end'   => ['line' => $last, 'character' => static::units($lines[$last])],
        ];
    }

    /**
     * Count the UTF-16 code units in the given line, as positions require.
     */
    protected static function units(string $line): int
    {
        return intdiv(strlen((string) mb_convert_encoding($line, 'UTF-16LE', 'UTF-8')), 2);
    }
}
