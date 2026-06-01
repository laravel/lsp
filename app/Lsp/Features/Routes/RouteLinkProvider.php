<?php

declare(strict_types=1);

namespace App\Lsp\Features\Routes;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class RouteLinkProvider implements LinkProvider
{
    /**
     * Create a new route link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide route document links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('routeLink', true)) {
            return [];
        }

        return (new RouteDocumentMapper($this->project))->links($document);
    }
}
