<?php

declare(strict_types=1);

namespace App\Lsp\Features\Storage;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class StorageLinkProvider implements LinkProvider
{
    /**
     * Create a new storage link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide storage disk links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('storageLink', true)) {
            return [];
        }

        return (new StorageDocumentMapper($this->project))->links($document);
    }
}
