<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class Env implements DataProvider
{
    /**
     * Create a new env provider instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get env-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            '.env',
        ];
    }

    /**
     * Get env variables keyed by name.
     */
    public function get(): Collection
    {
        $path = $this->project->path('.env');

        if (!is_file($path)) {
            return collect();
        }

        return collect(explode("\n", (string) file_get_contents($path)))
            ->map(fn (string $line, int $index): array => [
                'line'       => trim($line),
                'lineNumber' => $index + 1,
            ])
            ->reject(fn (array $item): bool => $item['line'] === '' || str_starts_with($item['line'], '#'))
            ->mapWithKeys(function (array $item): array {
                [$key, $value] = array_pad(explode('=', $item['line'], 2), 2, '');

                return [trim($key) => [
                    'value'      => trim($value),
                    'lineNumber' => $item['lineNumber'],
                ]];
            });
    }
}
