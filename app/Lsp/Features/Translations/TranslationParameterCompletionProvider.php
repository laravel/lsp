<?php

declare(strict_types=1);

namespace App\Lsp\Features\Translations;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\AutocompleteArguments;
use App\Lsp\Detection\Pattern;
use App\Lsp\Document;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class TranslationParameterCompletionProvider implements CompletionProvider
{
    /**
     * Create a new translation parameter completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide translation parameter completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('translationCompletion', true)) {
            return [];
        }

        return AutocompleteArguments::in($document, $position)
            ->matching($this->patterns())
            ->values()
            ->filter(fn (AutocompleteArgument $argument): bool => $argument->isArray() && $argument->isArrayKeyCompletion())
            ->flatMap(fn (AutocompleteArgument $argument): array => $this->toCompletions($argument))
            ->values()
            ->all();
    }

    /**
     * Get translation parameter completion patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(method: 'get', class: Pattern::contract('Translation\\Translator'), argument: 1),
            Pattern::method(method: 'choice', class: Pattern::contract('Translation\\Translator'), argument: 2),
            Pattern::method(method: ['__', 'trans', '@lang'], argument: 1),
            Pattern::method(method: 'trans_choice', argument: 2),
            Pattern::method(method: 'get', class: Pattern::facade('Lang'), argument: 1),
            Pattern::method(method: 'choice', class: Pattern::facade('Lang'), argument: 2),
        ];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        $translationKey = $argument->stringValueAt(0);

        if ($translationKey === null) {
            return [];
        }

        $group = $this->translationGroups()->firstWhere('key', str_replace('\\', '', $translationKey));
        $translation = is_array($group) ? $group['locales'] : null;

        if (!is_array($translation)) {
            return [];
        }

        $item = $this->defaultTranslation($translation);

        if (!is_array($item)) {
            return [];
        }

        return collect($item['params'] ?? [])
            ->filter(fn (mixed $parameter): bool => is_string($parameter) && $parameter !== '')
            ->map(fn (string $parameter): array => [
                'label'    => $parameter,
                'kind'     => 6,
                'textEdit' => [
                    'range'   => $argument->replacementRange(),
                    'newText' => $parameter,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Get the default translation item, falling back to the first locale.
     *
     * @param  array<string, array<string, mixed>>  $translation
     * @return array<string, mixed>|null
     */
    protected function defaultTranslation(array $translation): ?array
    {
        $item = $translation[$this->defaultLocale()] ?? reset($translation);

        return is_array($item) ? $item : null;
    }

    /**
     * Get the available translation groups.
     *
     * @return Collection<int, array{key: string, locales: array<string, array<string, mixed>>}>
     */
    protected function translationGroups(): Collection
    {
        return collect($this->translationData()['translations'] ?? [])
            ->filter(fn (mixed $translation): bool => is_array($translation))
            ->map(fn (array $locales, string $key): array => [
                'key'     => $key,
                'locales' => $locales,
            ])
            ->values();
    }

    /**
     * Get the default translation locale.
     */
    protected function defaultLocale(): string
    {
        return (string) ($this->translationData()['default'] ?? '');
    }

    /**
     * Get the raw translation data.
     *
     * @return array<string, mixed>
     */
    protected function translationData(): array
    {
        return $this->project->index->translations();
    }
}
