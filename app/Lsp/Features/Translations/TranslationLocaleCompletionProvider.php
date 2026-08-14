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

class TranslationLocaleCompletionProvider implements CompletionProvider
{
    /**
     * Create a new translation locale completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide translation locale completions for the given document and position.
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
            ->filter(fn (AutocompleteArgument $argument): bool => $this->isLocaleArgument($argument))
            ->flatMap(fn (AutocompleteArgument $argument): array => $this->toCompletions($argument))
            ->values()
            ->all();
    }

    /**
     * Get translation locale completion patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(method: ['get', 'string', 'choice'], class: Pattern::contract('Translation\\Translator'), argument: [0, 1, 2, 3]),
            Pattern::method(method: ['has', 'hasForLocale', 'get', 'string', 'choice'], class: Pattern::facade('Lang'), argument: [0, 1, 2, 3]),
            Pattern::method(method: ['get', 'string'], class: 'trans', argument: [0, 1, 2, 3]),
            Pattern::method(method: ['__', 'trans', 'trans_choice', '@lang'], argument: [0, 1, 2, 3]),
        ];
    }

    /**
     * Determine if the current argument is a locale argument.
     */
    protected function isLocaleArgument(AutocompleteArgument $argument): bool
    {
        if ($argument->isArgumentNamed('locale')) {
            return true;
        }

        $method = $argument->item()['methodName'] ?? null;
        $class = $argument->item()['className'] ?? '';
        $indexes = [
            Pattern::contract('Translation\\Translator') => ['get' => 2, 'string' => 2, 'choice' => 3],
            'Lang'                                       => ['has' => 1, 'hasForLocale' => 1, 'get' => 2, 'string' => 2, 'choice' => 3],
            Pattern::support('Facades\\Lang')            => ['has' => 1, 'hasForLocale' => 1, 'get' => 2, 'string' => 2, 'choice' => 3],
            'trans'                                      => ['get' => 2, 'string' => 2],
            ''                                           => ['__' => 2, 'trans' => 2, '@lang' => 2, 'trans_choice' => 3],
        ];

        return is_string($method)
            && ($indexes[$class][$method] ?? null) === $argument->argumentIndex();
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        return $this->languages()
            ->map(fn (string $language): array => [
                'label'    => $language,
                'kind'     => 12,
                'textEdit' => [
                    'range'   => $argument->replacementRange(),
                    'newText' => $language,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Get available translation languages.
     *
     * @return Collection<int, string>
     */
    protected function languages(): Collection
    {
        return collect($this->project->index->translations()['languages'] ?? [])
            ->filter(fn (mixed $language): bool => is_string($language) && $language !== '')
            ->values();
    }
}
