<?php

declare(strict_types=1);

namespace App\Lsp\Features\Views;

use App\Lsp\Contracts\HoverProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ViewHoverProvider implements HoverProvider
{
    /**
     * Create a new view hover provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide view hover for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function get(Document $document, array $position): ?array
    {
        if (!$this->project->boolean('viewHover', true)) {
            return null;
        }

        return (new ViewDocumentMapper($this->project))->hover($document, $position);
    }
}
