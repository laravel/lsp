<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;

class Tests implements DataProvider
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get the tests template to run.
     */
    public function template(): string
    {
        return file_get_contents(__DIR__ . '/Templates/tests.php') ?: '';
    }

    /**
     * Parse the raw tests data.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array<int, array<string, mixed>>
     */
    public function parse(array $data): array
    {
        return $data;
    }

    /**
     * Get data.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $data = $this->project->scripts->json($this->template());

        return $this->parse(is_array($data) ? $data : []);
    }

    /**
     * Get test-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            'tests/**/*',
            'phpunit.xml',
            'phpunit.xml.dist',
        ];
    }
}
