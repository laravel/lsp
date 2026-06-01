<?php

declare(strict_types=1);

namespace App\Lsp\Features\BladeComponents;

use App\Lsp\Contracts\HoverProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class BladeComponentHoverProvider implements HoverProvider
{
    /**
     * Create a new Blade component hover provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide Blade component hover for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function get(Document $document, array $position): ?array
    {
        if (!$this->project->boolean('bladeComponentHover', true)) {
            return null;
        }

        return (new BladeComponentDocumentMapper($this->project))->hover($document, $position);
    }
}
