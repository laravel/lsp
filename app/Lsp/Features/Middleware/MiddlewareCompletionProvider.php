<?php

declare(strict_types=1);

namespace App\Lsp\Features\Middleware;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class MiddlewareCompletionProvider implements CompletionProvider
{
    /**
     * Create a new middleware completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide middleware completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('middlewareCompletion', true)) {
            return [];
        }

        return (new MiddlewareDocumentMapper($this->project))->completions($document, $position);
    }
}
