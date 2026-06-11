<?php

declare(strict_types=1);

namespace App\Lsp\Features\BladeComponents;

use App\Lsp\Document;
use App\Lsp\Support\Position;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class BladeComponentDocumentMapper
{
    /**
     * Create a new Blade component document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get Blade component document links.
     *
     * @return array<int, array<string, mixed>>
     */
    public function links(Document $document): array
    {
        return collect($this->matches($document))
            ->map(function (array $match): ?array {
                $component = $this->component($match['name']);
                $path = $this->path($component);

                return $path ? $this->project->link($match['range'], $path) : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get Blade component hover for the given position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function hover(Document $document, array $position): ?array
    {
        foreach ($this->matches($document) as $match) {
            if (!Position::inRange($match['range'], $position)) {
                continue;
            }

            $component = $this->component($match['name']);

            if (!is_array($component)) {
                continue;
            }

            $lines = collect($component['paths'] ?? [])
                ->filter(fn (mixed $path): bool => is_string($path))
                ->map(fn (string $path): string => $this->markdownPath($path))
                ->all();

            if (is_string($component['props'] ?? null)) {
                $lines[] = "```blade\n{$component['props']}\n```";
            } elseif (is_iterable($component['props'] ?? null)) {
                foreach ($component['props'] as $prop) {
                    if (!is_array($prop)) {
                        continue;
                    }

                    $default = isset($prop['default']) && $prop['default'] !== null ? " = {$prop['default']}" : '';
                    $lines[] = '`' . ($prop['type'] ?? 'mixed') . '` `' . ($prop['name'] ?? '') . "`{$default}";
                }
            }

            return $lines === [] ? null : $this->markdownHover($match['range'], $lines);
        }

        return null;
    }

    /**
     * Get Blade component completions.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function completions(Document $document, array $position): array
    {
        $prefix = $this->completionPrefix($document, $position);

        if ($prefix === null) {
            return [];
        }

        $components = $this->project->index->bladeComponents()['components'] ?? [];

        return collect(array_keys(is_array($components) ? $components : []))
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->map(fn (string $key): array => [
                'label'    => $this->completionLabel($key),
                'textEdit' => [
                    'range'   => [
                        'start' => [
                            'line'      => $position['line'],
                            'character' => $position['character'] - strlen($prefix),
                        ],
                        'end' => [
                            'line'      => $position['line'],
                            'character' => $position['character'],
                        ],
                    ],
                    'newText' => $this->completionLabel($key),
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Find Blade component tag matches.
     *
     * @return array<int, array{name: string, range: array<string, array<string, int>>}>
     */
    protected function matches(Document $document): array
    {
        $matches = [];
        $prefixes = $this->project->index->bladeComponents()['prefixes'] ?? [];
        $prefixPattern = collect($prefixes)->filter()->map(fn (string $prefix): string => preg_quote($prefix, '/'))->implode('|');
        $patterns = ['/<\/?x-([^\s>]+)/'];

        if ($prefixPattern !== '') {
            $patterns[] = '/<\/?((' . $prefixPattern . ')\:[^\s>]+)/';
        }

        foreach (explode("\n", $document->content) as $lineNumber => $line) {
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $line, $lineMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
                    continue;
                }

                foreach ($lineMatches as $match) {
                    $matches[] = [
                        'name'  => $match[1][0],
                        'range' => [
                            'start' => ['line' => $lineNumber, 'character' => $match[0][1] + 1],
                            'end'   => ['line' => $lineNumber, 'character' => $match[0][1] + strlen($match[0][0])],
                        ],
                    ];
                }
            }
        }

        return $matches;
    }

    /**
     * Get a component by name.
     *
     * @return array<string, mixed>|null
     */
    protected function component(string $name): ?array
    {
        $components = $this->project->index->bladeComponents()['components'] ?? [];

        return is_array($components[$name] ?? null) ? $components[$name] : null;
    }

    /**
     * Get the component prefix being completed.
     *
     * @param  array<string, mixed>  $position
     */
    protected function completionPrefix(Document $document, array $position): ?string
    {
        $lineNumber = $position['line'] ?? null;
        $character = $position['character'] ?? null;

        if (!is_int($lineNumber) || !is_int($character)) {
            return null;
        }

        $line = explode("\n", $document->content)[$lineNumber] ?? '';
        $linePrefix = substr($line, 0, $character);

        return $this->componentPrefixes()
            ->first(fn (string $prefix): bool => str_ends_with($linePrefix, $prefix));
    }

    /**
     * Get Blade component completion prefixes.
     *
     * @return Collection<int, string>
     */
    protected function componentPrefixes(): Collection
    {
        $prefixes = $this->project->index->bladeComponents()['prefixes'] ?? [];

        return collect(['x', 'x-'])
            ->merge($prefixes)
            ->filter(fn (mixed $prefix): bool => is_string($prefix) && $prefix !== '')
            ->sortByDesc(fn (string $prefix): int => strlen($prefix))
            ->values();
    }

    /**
     * Get the completion label for the component key.
     */
    protected function completionLabel(string $key): string
    {
        if (str_contains($key, '::') || !str_contains($key, ':')) {
            return "x-{$key}";
        }

        return $key;
    }

    /**
     * Get the preferred component path.
     *
     * @param  array<string, mixed>|null  $component
     */
    protected function path(?array $component): ?string
    {
        if ($component === null || !is_iterable($component['paths'] ?? null)) {
            return null;
        }

        foreach ($component['paths'] as $path) {
            if (is_string($path) && str_ends_with($path, '.blade.php')) {
                return $path;
            }
        }

        return is_string($component['paths'][0] ?? null) ? $component['paths'][0] : null;
    }

    /**
     * Create a markdown hover response.
     *
     * @param  array<string, array<string, int>>  $range
     * @param  array<int, string>  $lines
     * @return array<string, mixed>
     */
    protected function markdownHover(array $range, array $lines): array
    {
        return [
            'range'    => $range,
            'contents' => [
                'kind'  => 'markdown',
                'value' => implode("\n\n", array_values(array_filter($lines))),
            ],
        ];
    }

    /**
     * Get a markdown link for a workspace path.
     */
    protected function markdownPath(string $path): string
    {
        return "[{$path}]({$this->project->target($path)})";
    }
}
