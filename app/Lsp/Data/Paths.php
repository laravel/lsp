<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class Paths implements DataProvider
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get the paths template to run.
     */
    public function template(): string
    {
        return file_get_contents(__DIR__ . '/Templates/paths.php') ?: '';
    }

    /**
     * Parse the raw paths data.
     *
     * @param  array<int, array<string, string>>  $data
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
     * Get path-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            'config/{,*,**/*}.php',
        ];
    }
}
