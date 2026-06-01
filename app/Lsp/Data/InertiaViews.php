<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Project;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class InertiaViews extends DataProvider
{
    /**
     * Create a new inertia views provider instance.
     */
    public function __construct(protected Project $project)
    {
        parent::__construct($project->scripts);
    }

    /**
     * Get the inertia template to run.
     */
    public function template(): string
    {
        return file_get_contents(__DIR__ . '/Templates/inertia.php') ?: '';
    }

    /**
     * Parse the raw inertia config data.
     *
     * @param  array<string, mixed>  $data
     * @return array{views: Collection<string, array<string, string>>, page_paths: Collection<int, string>, page_extensions: Collection<int, string>}
     */
    public function parse(array $data): array
    {
        $paths = $this->normalizePagePaths($data);
        $extensions = $this->normalizePageExtensions($data);

        return [
            'views' => $paths
                ->flatMap(fn (string $path): Collection => $this->discoverViews($path, $extensions))
                ->keyBy('name'),
            'page_paths'      => $paths,
            'page_extensions' => $extensions,
        ];
    }

    /**
     * Get inertia-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            'resources/js/Pages/{*,**/*}',
            'resources/js/pages/{*,**/*}',
            'config/{,*,**/*}.php',
        ];
    }

    /**
     * Get the default inertia view data.
     *
     * @return array{views: Collection<string, array<string, string>>, page_paths: Collection<int, string>, page_extensions: Collection<int, string>}
     */
    protected function default(): array
    {
        return [
            'views'           => collect(),
            'page_paths'      => collect(['resources/js/Pages']),
            'page_extensions' => collect(['vue']),
        ];
    }

    /**
     * Normalize Inertia page paths.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, string>
     */
    protected function normalizePagePaths(array $data): Collection
    {
        $paths = collect($data['page_paths'] ?? [])
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values();

        return $paths->isEmpty() ? collect(['resources/js/Pages']) : $paths;
    }

    /**
     * Normalize Inertia page extensions.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, string>
     */
    protected function normalizePageExtensions(array $data): Collection
    {
        $extensions = collect($data['page_extensions'] ?? [])
            ->filter(fn (mixed $extension): bool => is_string($extension) && $extension !== '')
            ->map(fn (string $extension): string => ltrim($extension, '.'))
            ->values();

        return $extensions->isEmpty() ? collect(['vue']) : $extensions;
    }

    /**
     * Discover Inertia views under a page path.
     *
     * @param  Collection<int, string>  $extensions
     * @return Collection<int, array<string, string>>
     */
    protected function discoverViews(string $path, Collection $extensions): Collection
    {
        $absolute = $this->project->path($path);

        if (!is_dir($absolute)) {
            return collect();
        }

        return collect(Finder::create()->files()->in($absolute))
            ->filter(fn (SplFileInfo $file): bool => $extensions->contains($file->getExtension()))
            ->map(function (SplFileInfo $file) use ($absolute, $path): array {
                $relative = ltrim(str_replace($absolute, '', $file->getRealPath() ?: $file->getPathname()), DIRECTORY_SEPARATOR);
                $name = preg_replace('/\.[^.]+$/', '', $relative) ?: $relative;

                return [
                    'name' => str_replace('\\', '/', $name),
                    'path' => trim($path, '/') . '/' . str_replace('\\', '/', $relative),
                ];
            })
            ->values();
    }
}
