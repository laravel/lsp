<?php

declare(strict_types=1);

namespace App\Lsp\Features\Assets;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class AssetDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new asset diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide asset diagnostics.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('assetDiagnostics', true)) {
            return [];
        }

        return (new AssetDocumentMapper($this->project))->diagnostics($document);
    }
}
