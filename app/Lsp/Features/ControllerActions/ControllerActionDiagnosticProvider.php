<?php

declare(strict_types=1);

namespace App\Lsp\Features\ControllerActions;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ControllerActionDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new controller action diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide controller action diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('controllerActionDiagnostics', true)) {
            return [];
        }

        return (new ControllerActionDocumentMapper($this->project))->diagnostics($document);
    }
}
