<?php

declare(strict_types=1);

namespace App\Lsp\Features\AppBindings;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class AppBindingCompletionProvider implements CompletionProvider
{
    /**
     * Create a new app binding completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide app binding completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('appBindingCompletion', true)) {
            return [];
        }

        return (new AppBindingDocumentMapper($this->project))->completions($document, $position);
    }
}
