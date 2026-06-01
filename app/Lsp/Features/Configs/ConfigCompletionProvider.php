<?php

declare(strict_types=1);

namespace App\Lsp\Features\Configs;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class ConfigCompletionProvider implements CompletionProvider
{
    /**
     * Create a new config completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide config completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('configCompletion', true)) {
            return [];
        }

        return (new ConfigDocumentMapper($this->project))->completions($document, $position);
    }
}
