<?php

declare(strict_types=1);

namespace App\Lsp\Features\Paths;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\Pattern;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Support\FileUri;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class PathDocumentMapper extends DocumentMapper
{
    /**
     * Create a new path document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get path helper detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(method: ['base_path', 'resource_path', 'config_path', 'app_path', 'database_path', 'lang_path', 'public_path', 'storage_path'], argument: 0),
        ];
    }

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toLinks(DetectedArgument $argument): array
    {
        $method = $argument->item()['methodName'] ?? null;
        $base = is_string($method)
            ? $this->paths()->firstWhere('key', $method)
            : null;
        $value = $argument->stringValue();

        if (!is_array($base) || !is_string($base['path'] ?? null) || $value === null) {
            return [];
        }

        $path = rtrim($base['path'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($value, DIRECTORY_SEPARATOR);

        return is_file($path)
            ? [[
                'range'  => $argument->range(),
                'target' => (string) FileUri::fromPath($path),
            ]]
            : [];
    }

    /**
     * Convert the given argument to hover.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    protected function toHover(DetectedArgument $argument, array $position): ?array
    {
        return null;
    }

    /**
     * Convert the given argument to diagnostics.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toDiagnostics(DetectedArgument $argument): array
    {
        return [];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        return [];
    }

    /**
     * Get the available base paths.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function paths(): Collection
    {
        return $this->project->index->paths();
    }
}
