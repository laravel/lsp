<?php

declare(strict_types=1);

namespace App\Lsp\Features\Translations;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\Pattern;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class TranslationDocumentMapper extends DocumentMapper
{
    /**
     * Create a new translation document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get translation detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(method: ['get', 'string', 'array', 'choice'], class: Pattern::contract('Translation\\Translator'), argument: 0),
            Pattern::method(method: ['has', 'hasForLocale', 'get', 'string', 'array', 'choice'], class: Pattern::facade('Lang'), argument: 0),
            Pattern::method(method: ['get', 'string', 'array'], class: 'trans', argument: 0),
            Pattern::method(method: ['__', 'trans', 'trans_choice', '@lang'], argument: 0),
        ];
    }

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toLinks(DetectedArgument $argument): array
    {
        $translation = $this->find($argument->stringValue());

        if (!is_array($translation)) {
            return [];
        }

        $lang = $this->lang($argument);
        $default = $this->defaultLocale();
        $item = $translation[$lang] ?? $translation[$default] ?? reset($translation);

        return is_array($item) && is_string($item['path'] ?? null)
            ? [$this->project->link($argument->range(), $item['path'], is_numeric($item['line'] ?? null) ? (int) $item['line'] : null)]
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
        $translation = $this->find($argument->stringValue());

        if (!is_array($translation)) {
            return null;
        }

        $lines = collect($translation)
            ->map(fn (array $item, string $locale): string => "`{$locale}`: {$item['value']}\n\n" . $this->markdownPath((string) $item['path'], is_numeric($item['line'] ?? null) ? (int) $item['line'] : null))
            ->values()
            ->all();

        return [
            'range'    => $argument->range(),
            'contents' => [
                'kind'  => 'markdown',
                'value' => implode("\n\n", array_values(array_filter($lines))),
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

        if ($value === null || !$this->isLikelyKeyLiteral($value) || is_array($this->find($value))) {
            return [];
        }

        return [[
            'range'    => $argument->range(),
            'severity' => 2,
            'source'   => 'Laravel Extension',
            'code'     => 'translation',
            'message'  => "Translation [{$value}] not found.",
        ]];
    }

    /**
     * Determine if the given translation literal looks like a key.
     */
    protected function isLikelyKeyLiteral(string $value): bool
    {
        return preg_match(
            '/^(?:[A-Za-z0-9_\\-\\/]+::)?[A-Za-z0-9_\\-\\/]+(?:\\.[A-Za-z0-9_\\-\\/]+)+$/',
            $value,
        ) === 1;
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        $translations = $this->translationGroups();
        $includeDetail = $translations->count() < 200;

        return $translations
            ->map(function (array $group) use ($argument, $includeDetail): array {
                $key = $group['key'];
                $translation = $group['locales'];
                $item = [
                    'label'    => $key,
                    'kind'     => 12,
                    'textEdit' => [
                        'range'   => $argument->replacementRange(),
                        'newText' => $this->completionText($key, $argument),
                    ],
                ];

                $default = $this->defaultTranslation($translation);

                if ($includeDetail && is_array($default) && is_string($default['value'] ?? null)) {
                    $item['detail'] = $default['value'];
                }

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * Resolve the requested locale from a detected call.
     */
    protected function lang(DetectedArgument $argument): ?string
    {
        $method = $argument->item()['methodName'] ?? null;
        $class = $argument->item()['className'] ?? '';
        $indexes = [
            Pattern::contract('Translation\\Translator') => ['get' => 2, 'string' => 2, 'array' => 2, 'choice' => 3],
            'Lang'                                       => ['has' => 1, 'hasForLocale' => 1, 'get' => 2, 'string' => 2, 'array' => 2, 'choice' => 3],
            Pattern::support('Facades\\Lang')            => ['has' => 1, 'hasForLocale' => 1, 'get' => 2, 'string' => 2, 'array' => 2, 'choice' => 3],
            'trans'                                      => ['get' => 2, 'string' => 2, 'array' => 2],
            ''                                           => ['__' => 2, 'trans' => 2, '@lang' => 2, 'trans_choice' => 3],
        ];

        $index = is_string($method) ? ($indexes[$class][$method] ?? null) : null;

        if ($index === null) {
            return null;
        }

        $arg = $argument->item()['arguments']['children'][$index]['children'][0] ?? null;

        return is_array($arg) && ($arg['type'] ?? null) === 'string' && is_string($arg['value'] ?? null)
            ? $arg['value']
            : null;
    }

    /**
     * Get a markdown link for a workspace path.
     */
    protected function markdownPath(string $path, ?int $line = null): string
    {
        return "[{$path}]({$this->project->target($path, $line)})";
    }

    /**
     * Get the completion insertion text for a translation key.
     */
    protected function completionText(string $key, AutocompleteArgument $argument): string
    {
        return match ($argument->precedingCharacter()) {
            "'"     => str_replace("'", "\\'", $key),
            '"'     => str_replace('"', '\\"', $key),
            default => $key,
        };
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
     * Find a translation by exact key or matching prefix.
     *
     * @return array<string, array<string, mixed>>|null
     */
    protected function find(?string $key): ?array
    {
        if ($key === null) {
            return null;
        }

        $key = str_replace('\\', '', $key);
        $group = $this->translationGroups()->firstWhere('key', $key);

        if (is_array($group)) {
            return $group['locales'];
        }

        $group = $this->translationGroups()
            ->first(fn (array $group): bool => str_starts_with($group['key'], "{$key}."));

        return is_array($group) ? $group['locales'] : null;
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
