<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Project;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Assets extends DataProvider
{
    /**
     * Create a new assets provider instance.
     */
    public function __construct(protected Project $project)
    {
        parent::__construct($project->scripts);
    }

    /**
     * Get the assets template to run.
     */
    public function template(): string
    {
        return '';
    }

    /**
     * Parse raw asset data.
     */
    public function parse(array $data): Collection
    {
        return collect($data);
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
        if ($this->loaded) {
            return $this->data;
        }

        $public = $this->project->path('public');

        $this->loaded = true;

        if (!is_dir($public)) {
            return $this->data = collect();
        }

        return $this->data = collect(Finder::create()->files()->in($public)->depth('<= 10'))
            ->reject(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->map(fn (SplFileInfo $file): array => [
                'path'     => str_replace('\\', '/', ltrim(str_replace($public, '', $file->getRealPath() ?: $file->getPathname()), DIRECTORY_SEPARATOR)),
                'fullPath' => $file->getRealPath() ?: $file->getPathname(),
            ])
            ->values();
    }
}
