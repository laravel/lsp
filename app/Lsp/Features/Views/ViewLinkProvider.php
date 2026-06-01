<?php

declare(strict_types=1);

namespace App\Lsp\Features\Views;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ViewLinkProvider implements LinkProvider
{
    /**
     * Create a new view link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide view links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('viewLink', true)) {
            return [];
        }

        return (new ViewDocumentMapper($this->project))->links($document);
    }
}
