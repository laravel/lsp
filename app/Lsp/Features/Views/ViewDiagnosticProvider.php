<?php

declare(strict_types=1);

namespace App\Lsp\Features\Views;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ViewDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new view diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide view diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('viewDiagnostics', true)) {
            return [];
        }

        return (new ViewDocumentMapper($this->project))->diagnostics($document);
    }
}
