<?php

declare(strict_types=1);

namespace App\Lsp\Features\Configs;

use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Features\Support\ReferenceFeature;

class ConfigReferenceProvider extends ReferenceFeature
{
    /**
     * Get the mapper that detects config symbols.
     */
    protected function mapper(): DocumentMapper
    {
        return new ConfigDocumentMapper($this->project);
    }

    /**
     * Get the initialization option that enables config references.
     */
    protected function option(): string
    {
        return 'configReferences';
    }
}
