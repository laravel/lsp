<?php

declare(strict_types=1);

namespace App\Lsp\Features\Routes;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class RouteDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new route diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide route diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('routeDiagnostics', true)) {
            return [];
        }

        return (new RouteDocumentMapper($this->project))->diagnostics($document);
    }
}
