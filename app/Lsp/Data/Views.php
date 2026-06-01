<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class Views implements DataProvider
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get the views template to run.
     */
    public function template(): string
    {
        return file_get_contents(__DIR__ . '/Templates/views.php') ?: '';
    }

    /**
     * Parse the raw views data.
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
     * Get view-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            '**/{resources,Modules/*/resources}/views/**/*.blade.php',
        ];
    }
}
