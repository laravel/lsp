<?php

declare(strict_types=1);

namespace App\Lsp\Features\Routes;

use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Features\Support\ReferenceFeature;

class RouteReferenceProvider extends ReferenceFeature
{
    /**
     * Get the mapper that detects route symbols.
     */
    protected function mapper(): DocumentMapper
    {
        return new RouteDocumentMapper($this->project);
    }

    /**
     * Get the initialization option that enables route references.
     */
    protected function option(): string
    {
        return 'routeReferences';
    }
}
