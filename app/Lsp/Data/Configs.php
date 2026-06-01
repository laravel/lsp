<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;

class Configs implements DataProvider
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get the configs template to run.
     */
    public function template(): string
    {
        return file_get_contents(__DIR__ . '/Templates/configs.php') ?: '';
    }

    /**
     * Parse the raw config data.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    public function parse(array $data): array
    {
        return [
            'configs' => collect($data)->map(fn (array $item): array => [
                'name'  => $item['name'] ?? '',
                'value' => $item['value'] ?? null,
                'file'  => $item['file'] ?? null,
                'line'  => $item['line'] ?? null,
            ])->values(),
            'paths' => collect($data)
                ->pluck('file')
                ->filter(fn (mixed $path): bool => is_string($path))
                ->unique()
                ->values(),
        ];
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
     * Get config-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            'config/{,*,**/*}.php',
            '.env',
        ];
    }
}
