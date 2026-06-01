<?php

declare(strict_types=1);

namespace App\Lsp\Features\AppBindings;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\Pattern;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class AppBindingDocumentMapper extends DocumentMapper
{
    /**
     * Create a new app binding document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get app binding detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::attribute(
                class: [
                    'Illuminate\\Container\\Attributes\\Bind',
                    'Illuminate\\Container\\Attributes\\Give',
                ],
                argument: 0,
            ),
            Pattern::method(
                method: ['make', 'bound'],
                class: [
                    'Illuminate\\Contracts\\Container\\Container',
                    'Illuminate\\Contracts\\Foundation\\Application',
                ],
                argument: 0,
            ),
            Pattern::method(
                method: ['make', 'bound', 'isShared'],
                class: [
                    'App',
                    'Illuminate\\Support\\Facades\\App',
                    'app',
                ],
                argument: 0,
            ),
            Pattern::method(
                method: 'app',
                argument: 0,
            ),
        ];
    }

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toLinks(DetectedArgument $argument): array
    {
        $appBinding = $this->find($argument);

        if ($appBinding === null || !is_string($appBinding['path'] ?? null)) {
            return [];
        }

        return [
            $this->project->link(
                $argument->range(),
                $appBinding['path'],
                (int) ($appBinding['line'] ?? 1),
            ),
        ];
    }

    /**
     * Convert the given argument to hover.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    protected function toHover(DetectedArgument $argument, array $position): ?array
    {
        $appBinding = $this->find($argument);

        if ($appBinding === null || !is_string($appBinding['path'] ?? null)) {
            return null;
        }

        $class = (string) ($appBinding['class'] ?? '');
        $path = $appBinding['path'];
        $target = $this->project->target($path, (int) ($appBinding['line'] ?? 1));

        return [
            'range'    => $argument->range(),
            'contents' => [
                'kind'  => 'markdown',
                'value' => implode("\n\n", [
                    "`{$class}`",
                    "[{$path}]({$target})",
                ]),
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
            'code'     => 'appBinding',
            'message'  => "App binding [{$value}] not found.",
        ]];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        return $this->appBindings()
            ->filter(fn (array $binding): bool => is_string($binding['key'] ?? null) && $binding['key'] !== '')
            ->map(fn (array $binding): array => [
                'label'    => $binding['key'],
                'kind'     => 21,
                'textEdit' => [
                    'range'   => $argument->replacementRange(),
                    'newText' => $binding['key'],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Find the app binding for the given argument.
     *
     * @return array<string, mixed>|null
     */
    protected function find(DetectedArgument $argument): ?array
    {
        $value = $argument->stringValue();

        if ($value === null) {
            return null;
        }

        $appBinding = $this->appBindings()->firstWhere('key', $value);

        return is_array($appBinding) ? $appBinding : null;
    }

    /**
     * Get the available app bindings.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function appBindings(): Collection
    {
        return $this->project->index->appBindings()
            ->map(fn (array $binding, string $key): array => ['key' => $key, ...$binding])
            ->values();
    }
}
