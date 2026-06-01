<?php

declare(strict_types=1);

namespace App\Lsp\Features\Middleware;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class MiddlewareDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new middleware diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide middleware diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('middlewareDiagnostics', true)) {
            return [];
        }

        return (new MiddlewareDocumentMapper($this->project))->diagnostics($document);
    }
}
