<?php

declare(strict_types=1);

namespace App\Lsp\Features\Configs;

use App\Lsp\Contracts\DiagnosticProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ConfigDiagnosticProvider implements DiagnosticProvider
{
    /**
     * Create a new config diagnostic provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide config diagnostics for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('configDiagnostics', true)) {
            return [];
        }

        return (new ConfigDocumentMapper($this->project))->diagnostics($document);
    }
}
