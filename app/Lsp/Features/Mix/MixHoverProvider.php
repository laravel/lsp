<?php

declare(strict_types=1);

namespace App\Lsp\Features\Mix;

use App\Lsp\Contracts\HoverProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class MixHoverProvider implements HoverProvider
{
    /**
     * Create a new mix hover provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide mix hover for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function get(Document $document, array $position): ?array
    {
        if (!$this->project->boolean('mixHover', true)) {
            return null;
        }

        return (new MixDocumentMapper($this->project))->hover($document, $position);
    }
}
