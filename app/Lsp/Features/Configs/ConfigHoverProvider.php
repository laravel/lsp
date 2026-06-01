<?php

declare(strict_types=1);

namespace App\Lsp\Features\Configs;

use App\Lsp\Contracts\HoverProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ConfigHoverProvider implements HoverProvider
{
    /**
     * Create a new config hover provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide config hover for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function get(Document $document, array $position): ?array
    {
        if (!$this->project->boolean('configHover', true)) {
            return null;
        }

        return (new ConfigDocumentMapper($this->project))->hover($document, $position);
    }
}
