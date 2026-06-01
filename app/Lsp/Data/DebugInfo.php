<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;

class DebugInfo implements DataProvider
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get the debug info template to run.
     */
    public function template(): string
    {
        return file_get_contents(__DIR__ . '/Templates/debug-info.php') ?: '';
    }

    /**
     * Parse the raw debug info data.
     *
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    public function parse(array $data): array
    {
        return $data;
    }

    /**
     * Get data.
     *
     * @return array<string, string>
     */
    public function get(): array
    {
        $data = $this->project->scripts->json($this->template());

        return $this->parse(is_array($data) ? $data : []);
    }

    /**
     * Get debug info watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [];
    }
}
