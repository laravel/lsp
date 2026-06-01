<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Assets implements DataProvider
{
    /**
     * Create a new assets provider instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get asset-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            'public/**/*',
        ];
    }

    /**
     * Get discovered public assets.
     */
    public function get(): Collection
    {
        $public = $this->project->path('public');

        if (!is_dir($public)) {
            return collect();
        }

        return collect(Finder::create()->files()->in($public)->depth('<= 10'))
            ->reject(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->map(fn (SplFileInfo $file): array => [
                'path'     => str_replace('\\', '/', ltrim(str_replace($public, '', $file->getRealPath() ?: $file->getPathname()), DIRECTORY_SEPARATOR)),
                'fullPath' => $file->getRealPath() ?: $file->getPathname(),
            ])
            ->values();
    }
}
