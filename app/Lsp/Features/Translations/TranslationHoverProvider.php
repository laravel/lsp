<?php

declare(strict_types=1);

namespace App\Lsp\Features\Translations;

use App\Lsp\Contracts\HoverProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class TranslationHoverProvider implements HoverProvider
{
    /**
     * Create a new translation hover provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide translation hover for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function get(Document $document, array $position): ?array
    {
        if (!$this->project->boolean('translationHover', true)) {
            return null;
        }

        return (new TranslationDocumentMapper($this->project))->hover($document, $position);
    }
}
