<?php

declare(strict_types=1);

namespace App\Lsp\Features\Env;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class EnvCompletionProvider implements CompletionProvider
{
    /**
     * Create a new env completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide env completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('envCompletion', true)) {
            return [];
        }

        return (new EnvDocumentMapper($this->project))->completions($document, $position);
    }
}
