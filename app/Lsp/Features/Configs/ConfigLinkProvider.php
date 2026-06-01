<?php

declare(strict_types=1);

namespace App\Lsp\Features\Configs;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ConfigLinkProvider implements LinkProvider
{
    /**
     * Create a new config link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide config document links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('configLink', true)) {
            return [];
        }

        return (new ConfigDocumentMapper($this->project))->links($document);
    }
}
