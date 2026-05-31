<?php

namespace App\Lsp\Contracts;

interface DataProvider
{
    /**
     * Get data.
     */
    public function get(): mixed;

    /**
     * Patterns that reevaluate the data.
     *
     * @return array<int, string>
     */
    public function patterns(): array;
}
