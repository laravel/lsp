<?php

declare(strict_types=1);

namespace App\Lsp\Features\Inertia;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class InertiaDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new Inertia diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide Inertia diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('inertiaDiagnostics', true)) {
            return [];
        }

        return (new InertiaDocumentMapper($this->project))->diagnostics($document);
    }
}
