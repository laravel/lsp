<?php

declare(strict_types=1);

namespace App\Lsp\Features\Auth;

use App\Lsp\Contracts\HoverProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class AuthHoverProvider implements HoverProvider
{
    /**
     * Create a new auth hover provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide auth hover for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function get(Document $document, array $position): ?array
    {
        if (!$this->project->boolean('authHover', true)) {
            return null;
        }

        return (new AuthDocumentMapper($this->project))->hover($document, $position);
    }
}
