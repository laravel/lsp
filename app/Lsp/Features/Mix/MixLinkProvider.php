<?php

declare(strict_types=1);

namespace App\Lsp\Features\Mix;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class MixLinkProvider implements LinkProvider
{
    /**
     * Create a new mix link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide mix links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('mixLink', true)) {
            return [];
        }

        return (new MixDocumentMapper($this->project))->links($document);
    }
}
