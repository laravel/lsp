<?php

declare(strict_types=1);

namespace App\Lsp\Features\AppBindings;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class AppBindingLinkProvider implements LinkProvider
{
    /**
     * Create a new app binding link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide app binding document links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('appBindingLink', true)) {
            return [];
        }

        return (new AppBindingDocumentMapper($this->project))->links($document);
    }
}
