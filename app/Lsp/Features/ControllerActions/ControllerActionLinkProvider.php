<?php

declare(strict_types=1);

namespace App\Lsp\Features\ControllerActions;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ControllerActionLinkProvider implements LinkProvider
{
    /**
     * Create a new controller action link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide controller action document links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('controllerActionLink', true)) {
            return [];
        }

        return (new ControllerActionDocumentMapper($this->project))->links($document);
    }
}
