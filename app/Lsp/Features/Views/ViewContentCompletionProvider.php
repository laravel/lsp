<?php

declare(strict_types=1);

namespace App\Lsp\Features\Views;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\AutocompleteArguments;
use App\Lsp\Detection\Pattern;
use App\Lsp\Document;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class ViewContentCompletionProvider implements CompletionProvider
{
    /**
     * Create a new Content view completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide Content view completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('viewCompletion', true)) {
            return [];
        }

        return AutocompleteArguments::in($document, $position)
            ->matching($this->patterns())
            ->values()
            ->filter(fn (AutocompleteArgument $argument): bool => $this->isViewArgument($argument))
            ->flatMap(fn (AutocompleteArgument $argument): array => $this->toCompletions($argument, $this->views()))
            ->values()
            ->all();
    }

    /**
     * Get Content view completion patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::attribute(class: 'Illuminate\\Mail\\Mailables\\Content', argument: [0, 1, 2, 3, 4]),
        ];
    }

    /**
     * Determine if the Content argument accepts a view name.
     */
    protected function isViewArgument(AutocompleteArgument $argument): bool
    {
        info('Checking argument', [
            'index'    => $argument->argumentIndex(),
            'children' => $argument->item()['arguments']['children'],
        ]);

        return $argument->isArgumentNamed('view')
            || $argument->isArgumentNamed('markdown')
            || in_array($argument->argumentIndex(), [0, 3], true);
    }

    /**
     * Convert the given argument to completion items.
     *
     * @param  Collection<int, array<string, mixed>>  $views
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument, Collection $views): array
    {
        return $views
            ->filter(fn (array $view): bool => is_string($view['key'] ?? null) && $view['key'] !== '')
            ->map(fn (array $view): array => [
                'label'    => $view['key'],
                'kind'     => 21,
                'sortText' => ((bool) ($view['isVendor'] ?? false) ? '1' : '0') . $view['key'],
                'textEdit' => [
                    'range'   => $argument->replacementRange(),
                    'newText' => $view['key'],
                ],
            ])
            ->values()
            ->all();
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
