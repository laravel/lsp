<?php

declare(strict_types=1);

namespace App\Lsp\Features\LivewireComponents;

use App\Lsp\Contracts\HoverProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class LivewireComponentHoverProvider implements HoverProvider
{
    /**
     * Create a new Livewire component hover provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide Livewire component hover for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function get(Document $document, array $position): ?array
    {
        if (!$this->project->boolean('livewireComponentHover', true)) {
            return null;
        }

        return (new LivewireComponentDocumentMapper($this->project))->hover($document, $position);
    }
}
