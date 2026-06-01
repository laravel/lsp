<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Controllers implements DataProvider
{
    /**
     * Create a new controllers provider instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get controller-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            'app/Http/Controllers/*.php',
            'app/Http/Controllers/**/*.php',
        ];
    }

    /**
     * Get discovered controller actions.
     *
     * @return Collection<int, string>
     */
    public function get(): Collection
    {
        $path = $this->project->path('app/Http/Controllers');

        if (!is_dir($path)) {
            return collect();
        }

        return collect(Finder::create()->files()->name('*.php')->in($path))
            ->filter(fn (SplFileInfo $file): bool => $file->getSize() <= 50_000)
            ->flatMap(fn (SplFileInfo $file): array => $this->actionsIn((string) file_get_contents($file->getRealPath() ?: $file->getPathname())))
            ->unique()
            ->values();
    }

    /**
     * Get controller actions from the given PHP source.
     *
     * @return array<int, string>
     */
    protected function actionsIn(string $source): array
    {
        if (!preg_match('/class\s+([A-Za-z0-9_]+)\s+extends\s+.+/', $source, $classMatch)) {
            return [];
        }

        if (!preg_match('/namespace\s+(.+?);/', $source, $namespaceMatch)) {
            return [];
        }

        $namespace = $this->controllerNamespace($namespaceMatch[1]);

        if ($namespace === null) {
            return [];
        }

        preg_match_all('/public\s+function\s+([A-Za-z0-9_]+)\s*\(/', $source, $methodMatches);

        return collect($methodMatches[1] ?? [])
            ->reject(fn (string $method): bool => $method === '__construct')
            ->flatMap(fn (string $method): array => $this->actionNames($classMatch[1], $namespace, $method))
            ->values()
            ->all();
    }

    /**
     * Get the controller namespace relative to App\Http\Controllers.
     */
    protected function controllerNamespace(string $namespace): ?string
    {
        $namespace = trim($namespace);

        if (!preg_match('/\\\\Http\\\\Controllers(?:\\\\(.+))?$/', $namespace, $match)) {
            return null;
        }

        return $match[1] ?? '';
    }

    /**
     * Get completion action names for a controller method.
     *
     * @return array<int, string>
     */
    protected function actionNames(string $class, string $namespace, string $method): array
    {
        $action = $method === '__invoke' ? $class : "{$class}@{$method}";

        if ($namespace === '') {
            return [$action];
        }

        $namespaced = $method === '__invoke'
            ? "{$namespace}\\{$class}"
            : "{$namespace}\\{$class}@{$method}";

        return [$namespaced, $action];
    }
}
