<?php

declare(strict_types=1);

namespace App\Lsp\Features\BladeComponents;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class BladeComponentCompletionProvider implements CompletionProvider
{
    /**
     * Create a new Blade component completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide Blade component completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('bladeComponentCompletion', true)) {
            return [];
        }

        if (!str_ends_with($document->uri, '.blade.php')) {
            return [];
        }

        return (new BladeComponentDocumentMapper($this->project))->completions($document, $position);
    }
}
