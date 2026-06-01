<?php

declare(strict_types=1);

namespace App\Lsp\Features\Auth;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class AuthCompletionProvider implements CompletionProvider
{
    /**
     * Create a new auth completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide auth completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('authCompletion', true)) {
            return [];
        }

        return (new AuthDocumentMapper($this->project))->completions($document, $position);
    }
}
