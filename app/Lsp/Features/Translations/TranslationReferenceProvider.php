<?php

declare(strict_types=1);

namespace App\Lsp\Features\Translations;

use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Features\Support\ReferenceFeature;

class TranslationReferenceProvider extends ReferenceFeature
{
    /**
     * Get the mapper that detects translation symbols.
     */
    protected function mapper(): DocumentMapper
    {
        return new TranslationDocumentMapper($this->project);
    }

    /**
     * Get the initialization option that enables translation references.
     */
    protected function option(): string
    {
        return 'translationReferences';
    }
}
