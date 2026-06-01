<?php

declare(strict_types=1);

namespace App\Lsp\Features\Routes;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class RouteCompletionProvider implements CompletionProvider
{
    /**
     * Create a new route completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide route completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('routeCompletion', true)) {
            return [];
        }

        return (new RouteDocumentMapper($this->project))->completions($document, $position);
    }
}
