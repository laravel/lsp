<?php

declare(strict_types=1);

namespace App\Lsp\Features\Env;

use App\Lsp\Contracts\HoverProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class EnvHoverProvider implements HoverProvider
{
    /**
     * Create a new env hover provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide env hover for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function get(Document $document, array $position): ?array
    {
        if (!$this->project->boolean('envHover', true)) {
            return null;
        }

        return (new EnvDocumentMapper($this->project))->hover($document, $position);
    }
}
