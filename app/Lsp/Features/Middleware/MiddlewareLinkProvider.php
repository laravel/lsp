<?php

declare(strict_types=1);

namespace App\Lsp\Features\Middleware;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class MiddlewareLinkProvider implements LinkProvider
{
    /**
     * Create a new middleware link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide middleware links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('middlewareLink', true)) {
            return [];
        }

        return (new MiddlewareDocumentMapper($this->project))->links($document);
    }
}
