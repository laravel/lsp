<?php

declare(strict_types=1);

namespace App\Lsp\Features\LivewireComponents;

use App\Lsp\Document;
use App\Lsp\Support\Position;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class LivewireComponentDocumentMapper
{
    /**
     * Create a new Livewire component document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get Livewire component document links.
     *
     * @return array<int, array<string, mixed>>
     */
    public function links(Document $document): array
    {
        return collect($this->matches($document))
            ->map(function (array $match): ?array {
                $view = $this->livewireComponent($match['name']);

                return is_array($view) && is_string($view['path'] ?? null)
                    ? $this->project->link($match['range'], $view['path'])
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get Livewire component hover for the given position.
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

            $view = $this->livewireComponent($match['name']);
            $livewire = is_array($view) ? ($view['livewire'] ?? null) : null;

            if (!is_array($livewire)) {
                continue;
            }

            $lines = collect($livewire['files'] ?? [])
                ->filter(fn (mixed $path): bool => is_string($path))
                ->map(fn (string $path): string => "[{$path}]({$this->project->target($path)})")
                ->all();

            $props = collect($livewire['props'] ?? [])
                ->filter(fn (mixed $prop): bool => is_array($prop))
                ->map(fn (array $prop): string => ($prop['type'] ?? 'mixed') . ' $' . ($prop['name'] ?? '') . (($prop['hasDefaultValue'] ?? false) ? ' = ' . ($prop['defaultValue'] ?? '') : '') . ';')
                ->implode("\n");

            if ($props !== '') {
                $lines[] = "```php\n<?php\n{$props}\n```";
            }

            if ($lines === []) {
                return null;
            }

            return [
                'range'    => $match['range'],
                'contents' => [
                    'kind'  => 'markdown',
                    'value' => implode("\n\n", array_values(array_filter($lines))),
                ],
            ];
        }

        return null;
    }

    /**
     * Get Livewire component completions.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function completions(Document $document, array $position): array
    {
        if (!$this->isCompletingComponentTag($document, $position)) {
            return [];
        }

        return $this->project->index->views()
            ->filter(fn (array $view): bool => ($view['livewire'] ?? null) !== null && is_string($view['key'] ?? null) && $view['key'] !== '')
            ->map(fn (array $view): array => [
                'label'    => $this->completionLabel($view['key']),
                'kind'     => 21,
                'textEdit' => [
                    'range' => [
                        'start' => $position,
                        'end'   => $position,
                    ],
                    'newText' => $this->completionLabel($view['key']),
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Find Livewire tag matches.
     *
     * @return array<int, array{name: string, range: array<string, array<string, int>>}>
     */
    protected function matches(Document $document): array
    {
        $matches = [];

        foreach (explode("\n", $document->content) as $lineNumber => $line) {
            if (preg_match('/<\/?livewire:([^\s>]+)/', $line, $match, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $matches[] = [
                'name'  => $match[1][0],
                'range' => [
                    'start' => ['line' => $lineNumber, 'character' => $match[0][1] + 1],
                    'end'   => ['line' => $lineNumber, 'character' => $match[0][1] + strlen($match[0][0])],
                ],
            ];
        }

        return $matches;
    }

    /**
     * Determine if the cursor is completing a Livewire component tag.
     *
     * @param  array<string, mixed>  $position
     */
    protected function isCompletingComponentTag(Document $document, array $position): bool
    {
        $lineNumber = $position['line'] ?? null;
        $character = $position['character'] ?? null;

        if (!is_int($lineNumber) || !is_int($character)) {
            return false;
        }

        $line = explode("\n", $document->content)[$lineNumber] ?? '';

        return str_ends_with(substr($line, 0, $character), '<livewire:');
    }

    /**
     * Get the Livewire component completion label.
     */
    protected function completionLabel(string $key): string
    {
        return str_starts_with($key, 'livewire.')
            ? substr($key, strlen('livewire.'))
            : $key;
    }

    /**
     * Get a Livewire view by component name.
     *
     * @return array<string, mixed>|null
     */
    protected function livewireComponent(string $component): ?array
    {
        $view = $this->views()
            ->first(fn (array $view): bool => ($view['key'] ?? null) === "livewire.{$component}" || (($view['livewire'] ?? null) !== null && ($view['key'] ?? null) === $component));

        return is_array($view) ? $view : null;
    }

    /**
     * Get the available views.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function views(): Collection
    {
        return $this->project->index->views();
    }
}
