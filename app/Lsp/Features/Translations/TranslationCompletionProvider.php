<?php

declare(strict_types=1);

namespace App\Lsp\Features\Translations;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class TranslationCompletionProvider implements CompletionProvider
{
    /**
     * Create a new translation completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide translation key completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('translationCompletion', true)) {
            return [];
        }

        return (new TranslationDocumentMapper($this->project))->completions($document, $position);
    }
}
