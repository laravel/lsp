<?php

declare(strict_types=1);

namespace App\Lsp\Features\Mix;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class MixDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new mix diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide mix diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('mixDiagnostics', true)) {
            return [];
        }

        return (new MixDocumentMapper($this->project))->diagnostics($document);
    }
}
