<?php

declare(strict_types=1);

namespace App\Lsp\Features\Inertia;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class InertiaCompletionProvider implements CompletionProvider
{
    /**
     * Create a new Inertia completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide Inertia page completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('inertiaCompletion', true)) {
            return [];
        }

        return (new InertiaDocumentMapper($this->project))->completions($document, $position);
    }
}
