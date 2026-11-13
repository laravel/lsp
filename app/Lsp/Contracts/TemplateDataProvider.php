<?php

namespace App\Lsp\Contracts;

interface TemplateDataProvider extends DataProvider
{
    /**
     * Get the template that is run inside the user's application.
     */
    public function template(): string;

    /**
     * Parse the decoded template output.
     *
     * @param  array<mixed>  $data
     */
    public function parse(array $data): mixed;
}
