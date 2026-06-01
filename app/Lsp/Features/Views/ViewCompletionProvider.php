<?php

declare(strict_types=1);

namespace App\Lsp\Features\Views;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ViewCompletionProvider implements CompletionProvider
{
    /**
     * Create a new view completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide view completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('viewCompletion', true)) {
            return [];
        }

        return (new ViewDocumentMapper($this->project))->completions($document, $position);
    }
}
