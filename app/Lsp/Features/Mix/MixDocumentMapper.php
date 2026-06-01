<?php

declare(strict_types=1);

namespace App\Lsp\Features\Mix;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\Pattern;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class MixDocumentMapper extends DocumentMapper
{
    /**
     * Create a new mix document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get mix detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(method: 'mix', argument: 0),
        ];
    }

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toLinks(DetectedArgument $argument): array
    {
        $item = $this->find($argument);

        return $item !== null && is_string($item['path'] ?? null)
            ? [$this->project->link($argument->range(), $item['path'])]
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
        $item = $this->find($argument);

        if ($item === null || !is_string($item['path'] ?? null)) {
            return null;
        }

        return [
            'range'    => $argument->range(),
            'contents' => [
                'kind'  => 'markdown',
                'value' => "[{$item['path']}]({$this->project->target($item['path'])})",
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
            'code'     => 'mix',
            'message'  => "Mix manifest item [{$value}] not found.",
        ]];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        return $this->manifest()
            ->filter(fn (array $item): bool => is_string($item['key'] ?? null) && $item['key'] !== '')
            ->map(fn (array $item): array => [
                'label'    => $item['key'],
                'kind'     => 12,
                'textEdit' => [
                    'range'   => $argument->replacementRange(),
                    'newText' => $item['key'],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Find the mix manifest item for the given argument.
     *
     * @return array<string, mixed>|null
     */
    protected function find(DetectedArgument $argument): ?array
    {
        $value = $argument->stringValue();

        if ($value === null) {
            return null;
        }

        $item = $this->manifest()->firstWhere('key', $value);

        return is_array($item) ? $item : null;
    }

    /**
     * Get the available mix manifest items.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function manifest(): Collection
    {
        return $this->project->index->mixManifest();
    }
}
