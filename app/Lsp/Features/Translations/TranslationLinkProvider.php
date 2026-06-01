<?php

declare(strict_types=1);

namespace App\Lsp\Features\Translations;

use App\Lsp\Contracts\LinkProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class TranslationLinkProvider implements LinkProvider
{
    /**
     * Create a new translation link provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide translation links for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document): array
    {
        if (!$this->project->boolean('translationLink', true)) {
            return [];
        }

        return (new TranslationDocumentMapper($this->project))->links($document);
    }
}
