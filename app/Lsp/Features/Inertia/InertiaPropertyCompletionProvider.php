<?php

declare(strict_types=1);

namespace App\Lsp\Features\Inertia;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\AutocompleteArguments;
use App\Lsp\Detection\Pattern;
use App\Lsp\Document;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class InertiaPropertyCompletionProvider implements CompletionProvider
{
    /**
     * Create a new Inertia property completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide Inertia property completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('inertiaCompletion', true)) {
            return [];
        }

        $views = $this->views();

        return AutocompleteArguments::in($document, $position)
            ->matching($this->patterns())
            ->values()
            ->filter(fn (AutocompleteArgument $argument): bool => $argument->isArray() && $argument->isArrayKeyCompletion())
            ->flatMap(fn (AutocompleteArgument $argument): array => $this->toCompletions($argument, $views))
            ->values()
            ->all();
    }

    /**
     * Get Inertia property completion patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(method: ['render', 'modal'], class: 'Inertia\\Inertia', argument: 1),
            Pattern::method(method: 'inertia', argument: 1),
        ];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @param  Collection<int, array<string, mixed>>  $views
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument, Collection $views): array
    {
        $viewName = $argument->stringValueAt($argument->argumentIndex() - 1);

        if ($viewName === null) {
            return [];
        }

        $view = $views->firstWhere('name', $viewName);

        if (!is_array($view) || !is_string($view['path'] ?? null)) {
            return [];
        }

        $content = $this->viewContent($view['path']);

        if ($content === null) {
            return [];
        }

        $existingKeys = $argument->arrayKeys();

        return collect($this->props($content))
            ->reject(fn (string $prop): bool => in_array($prop, $existingKeys, true))
            ->map(fn (string $prop): array => [
                'label'    => $prop,
                'kind'     => 21,
                'textEdit' => [
                    'range'   => $argument->replacementRange(),
                    'newText' => $prop,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Get page file contents.
     */
    protected function viewContent(string $path): ?string
    {
        $absolute = $this->project->path($path);

        if (!is_file($absolute)) {
            return null;
        }

        $content = file_get_contents($absolute);

        return is_string($content) ? $content : null;
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

    /**
     * Extract Inertia page prop names from defineProps.
     *
     * @return array<int, string>
     */
    protected function props(string $content): array
    {
        $patterns = [
            [
                'regex'  => '/defineProps<({[^}>]+})>/s',
                'prefix' => 'defineProps<',
                'suffix' => '>',
            ],
            [
                'regex'  => '/defineProps\(({[^})]+})\)/s',
                'prefix' => 'defineProps(',
                'suffix' => ')',
            ],
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern['regex'], $content, $matches) !== 1) {
                continue;
            }

            $props = str_replace([$pattern['prefix'], $pattern['suffix']], '', $matches[0]);
            $props = str_replace('?:', ':', $props);
            $props = preg_replace('/\s/', '', $props) ?? '';
            $props = substr($props, 1, -1);
            $nestedLevel = 0;
            $names = [];

            foreach (explode(';', $props) as $prop) {
                if (str_contains($prop, '{')) {
                    $nestedLevel++;
                }

                if (str_contains($prop, '}')) {
                    $nestedLevel--;
                }

                if ($nestedLevel > 0 || !str_contains($prop, ':')) {
                    continue;
                }

                [$name] = explode(':', $prop, 2);

                if ($name !== '') {
                    $names[] = $name;
                }
            }

            return array_values(array_unique($names));
        }

        return [];
    }
}
