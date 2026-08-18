<?php

declare(strict_types=1);

namespace App\Lsp\Contracts;

use App\Lsp\Document;

interface ReferenceProvider
{
    /**
     * Get the symbol this provider owns at the given position, if any.
     *
     * @param  array<string, mixed>  $position
     */
    public function symbolAt(Document $document, array $position): ?string;

    /**
     * Get the ranges in the document that reference the given symbol.
     *
     * @return array<int, array<string, array<string, int>>>
     */
    public function ranges(Document $document, string $symbol): array;
}
