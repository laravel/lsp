<?php

declare(strict_types=1);

namespace App\Lsp\Features\Auth;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class AuthDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new auth diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide auth diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('authDiagnostics', true)) {
            return [];
        }

        return (new AuthDocumentMapper($this->project))->diagnostics($document);
    }
}
