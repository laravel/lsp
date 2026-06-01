<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;

class Routes implements DataProvider
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get data.
     */
    public function get(): mixed
    {
        return collect($this->project->scripts->json(file_get_contents(__DIR__ . '/Templates/routes.php')));
    }

    /**
     * Patterns that reevaluate the data.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            '**/{[Rr]oute}{,s}{.php,/*.php,/**/*.php}',
        ];
    }
}
