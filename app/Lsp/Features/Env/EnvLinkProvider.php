<?php

declare(strict_types=1);

namespace App\Lsp\Features\Env;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class EnvLinkProvider implements LinkProvider
{
    /**
     * Create a new env link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide env document links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('envLink', true)) {
            return [];
        }

        return (new EnvDocumentMapper($this->project))->links($document);
    }
}
