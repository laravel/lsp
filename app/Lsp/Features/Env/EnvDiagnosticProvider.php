<?php

declare(strict_types=1);

namespace App\Lsp\Features\Env;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class EnvDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new env diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide env diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('envDiagnostics', true)) {
            return [];
        }

        return (new EnvDocumentMapper($this->project))->diagnostics($document);
    }
}
