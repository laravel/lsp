<?php

declare(strict_types=1);

namespace App\Lsp\Features\Inertia;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\Pattern;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class InertiaDocumentMapper extends DocumentMapper
{
    /**
     * Create a new Inertia document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get Inertia detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(method: ['render', 'modal'], class: 'Inertia\\Inertia', argument: 0),
            Pattern::method(method: 'inertia', class: Pattern::facade('Route'), argument: 1),
            Pattern::method(method: 'inertia', argument: 0),
        ];
    }

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toLinks(DetectedArgument $argument): array
    {
        $view = $this->find($argument);

        return $view !== null && is_string($view['path'] ?? null)
            ? [$this->project->link($argument->range(), $view['path'])]
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
        $view = $this->find($argument);

        if ($view === null || !is_string($view['path'] ?? null)) {
            return null;
        }

        return [
            'range'    => $argument->range(),
            'contents' => [
                'kind'  => 'markdown',
                'value' => "[{$view['path']}]({$this->project->target($view['path'])})",
            ],
        ];
    }

    /**
     * Convert the given argument to diagnostics.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toDiagnostics(DetectedArgument $argument): array
    {
        $value = $argument->stringValue();

        if ($value === null || $this->find($argument) !== null) {
            return [];
        }

        return [[
            'range'    => $argument->range(),
            'severity' => 2,
            'source'   => 'Laravel Extension',
            'code'     => 'inertia',
            'message'  => "Inertia view [{$value}] not found.",
        ]];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        return $this->views()
            ->filter(fn (array $view): bool => is_string($view['name'] ?? null) && $view['name'] !== '')
            ->map(fn (array $view): array => [
                'label'    => $view['name'],
                'kind'     => 21,
                'textEdit' => [
                    'range'   => $argument->replacementRange(),
                    'newText' => $view['name'],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Find the Inertia view for the given argument.
     *
     * @return array<string, mixed>|null
     */
    protected function find(DetectedArgument $argument): ?array
    {
        $value = $argument->stringValue();

        if ($value === null) {
            return null;
        }

        $view = $this->views()->firstWhere('name', $value);

        return is_array($view) ? $view : null;
    }

    /**
     * Get the available Inertia views.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function views(): Collection
    {
        return $this->project->index->inertiaViews()['views']->values();
    }
}
