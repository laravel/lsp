<?php

declare(strict_types=1);

namespace App\Lsp\Features\Views;

use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Features\Support\ReferenceFeature;

class ViewReferenceProvider extends ReferenceFeature
{
    /**
     * Get the mapper that detects view symbols.
     */
    protected function mapper(): DocumentMapper
    {
        return new ViewDocumentMapper($this->project);
    }

    /**
     * Get the initialization option that enables view references.
     */
    protected function option(): string
    {
        return 'viewReferences';
    }
}
