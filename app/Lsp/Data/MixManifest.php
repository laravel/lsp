<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class MixManifest implements DataProvider
{
    /**
     * Create a new mix manifest provider instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get mix manifest-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            'public/mix-manifest.json',
        ];
    }

    /**
     * Get mix manifest items.
     */
    public function get(): Collection
    {
        $path = $this->project->path('public/mix-manifest.json');

        if (!is_file($path)) {
            return collect();
        }

        $items = json_decode((string) file_get_contents($path), true);

        if (!is_array($items)) {
            return collect();
        }

        return collect($items)
            ->map(fn (mixed $value, string $key): array => [
                'key'   => ltrim(str_replace('\\', '/', $key), '/'),
                'value' => ltrim(str_replace('\\', '/', (string) $value), '/'),
                'path'  => 'public/' . ltrim(str_replace('\\', '/', (string) $value), '/'),
            ])
            ->values();
    }
}
