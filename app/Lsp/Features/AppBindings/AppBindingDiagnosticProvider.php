<?php

declare(strict_types=1);

namespace App\Lsp\Features\AppBindings;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class AppBindingDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new app binding diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide app binding diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('appBindingDiagnostics', true)) {
            return [];
        }

        return (new AppBindingDocumentMapper($this->project))->diagnostics($document);
    }
}
