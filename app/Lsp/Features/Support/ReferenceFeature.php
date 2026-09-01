<?php

declare(strict_types=1);

namespace App\Lsp\Features\Support;

use App\Lsp\Contracts\ReferenceProvider;
use App\Lsp\Document;
use App\Lsp\Project;

/**
 * Exposes a document mapper as a reference provider.
 */
abstract class ReferenceFeature implements ReferenceProvider
{
    /**
     * Create a new reference feature instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get the mapper that detects this feature's symbols.
     */
    abstract protected function mapper(): DocumentMapper;

    /**
     * Get the initialization option that enables this feature.
     */
    abstract protected function option(): string;

    /**
     * Get the symbol this provider owns at the given position, if any.
     *
     * @param  array<string, mixed>  $position
     */
    public function symbolAt(Document $document, array $position): ?string
    {
        return $this->enabled()
            ? $this->mapper()->symbolAt($document, $position)
            : null;
    }

    /**
     * Get the ranges in the document that reference the given symbol.
     *
     * @return array<int, array<string, array<string, int>>>
     */
    public function ranges(Document $document, string $symbol): array
    {
        return $this->enabled()
            ? $this->mapper()->rangesFor($document, $symbol)
            : [];
    }

    /**
     * Determine if the feature is enabled.
     */
    protected function enabled(): bool
    {
        return $this->project->boolean($this->option(), true);
    }
}
