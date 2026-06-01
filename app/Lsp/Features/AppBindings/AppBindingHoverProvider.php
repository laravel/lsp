<?php

declare(strict_types=1);

namespace App\Lsp\Features\AppBindings;

use App\Lsp\Contracts\HoverProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class AppBindingHoverProvider implements HoverProvider
{
    /**
     * Create a new app binding hover provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide app binding hover for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function get(Document $document, array $position): ?array
    {
        if (!$this->project->boolean('appBindingHover', true)) {
            return null;
        }

        return (new AppBindingDocumentMapper($this->project))->hover($document, $position);
    }
}
