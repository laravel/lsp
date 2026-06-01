<?php

declare(strict_types=1);

namespace App\Lsp\Features\Storage;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class StorageDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new storage diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide storage disk diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('storageDiagnostics', true)) {
            return [];
        }

        return (new StorageDocumentMapper($this->project))->diagnostics($document);
    }
}
