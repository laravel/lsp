<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\TemplateDataProvider;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class Routes implements TemplateDataProvider
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get the routes template to run.
     */
    public function template(): string
    {
        return file_get_contents(__DIR__ . '/Templates/routes.php') ?: '';
    }

    /**
     * Parse the routes template output.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    public function parse(array $data): Collection
    {
        return collect($data);
    }

    /**
     * Get data.
     */
    public function get(): Collection
    {
        $data = $this->project->scripts->json($this->template());

        return $this->parse(is_array($data) ? $data : []);
    }

    /**
     * Patterns that reevaluate the data.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            '**/[Rr]oute{,s}{.php,/*.php,/**/*.php}',
        ];
    }
}
