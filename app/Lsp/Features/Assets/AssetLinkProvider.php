<?php

declare(strict_types=1);

namespace App\Lsp\Features\Assets;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class AssetLinkProvider implements LinkProvider
{
    /**
     * Create a new asset link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide asset links.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('assetLink', true)) {
            return [];
        }

        return (new AssetDocumentMapper($this->project))->links($document);
    }
}
