<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;

class BladeComponents implements DataProvider
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get the Blade components template to run.
     */
    public function template(): string
    {
        return file_get_contents(__DIR__ . '/Templates/blade-components.php') ?: '';
    }

    /**
     * Parse the raw Blade component data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function parse(array $data): array
    {
        return $data;
    }

    /**
     * Get data.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $data = $this->project->scripts->json($this->template());

        return $this->parse(is_array($data) ? $data : []);
    }

    /**
     * Get Blade component-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            '**/{resources,Modules/*/resources}/views/**/*.blade.php',
            'app/View/Components/{,*,**/*}.php',
        ];
    }
}
