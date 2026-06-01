<?php

declare(strict_types=1);

namespace App\Lsp\Features\Configs;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\Pattern;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class ConfigDocumentMapper extends DocumentMapper
{
    /**
     * Create a new config document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get config detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(method: 'config', argument: 0),
            Pattern::attribute(class: Pattern::containerAttribute('Config'), argument: 0),
            Pattern::method(method: ['get', 'prepend', 'push'], class: Pattern::contract('Config\\Repository'), argument: 0),
            Pattern::method(method: ['get', 'getMany', 'string', 'integer', 'boolean', 'float', 'array', 'prepend', 'push'], class: [...Pattern::facade('Config'), 'config'], argument: 0),
        ];
    }

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toLinks(DetectedArgument $argument): array
    {
        $config = $this->find($argument);

        if ($config === null || !is_string($config['file'] ?? null)) {
            return [];
        }

        return [
            $this->project->link(
                $argument->range(),
                $config['file'],
                is_numeric($config['line'] ?? null) ? (int) $config['line'] : null,
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
        $config = $this->find($argument);

        if ($config === null) {
            return null;
        }

        $lines = [];
        $value = $config['value'] ?? null;

        if ($value !== null) {
            $display = is_scalar($value) ? (string) $value : 'array(...)';

            $lines[] = '`' . ($display === '' ? '[empty string]' : $display) . '`';
        }

        if (is_string($config['file'] ?? null)) {
            $line = is_numeric($config['line'] ?? null) ? (int) $config['line'] : null;
            $target = $this->project->target($config['file'], $line);

            $lines[] = "[{$config['file']}]({$target})";
        }

        if ($lines === []) {
            return null;
        }

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

        if ($value === null || $this->find($argument) !== null) {
            return [];
        }

        return [[
            'range'    => $argument->range(),
            'severity' => 2,
            'source'   => 'Laravel Extension',
            'code'     => 'config',
            'message'  => "Config [{$value}] not found.",
        ]];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        if (($argument->item()['methodName'] ?? null) === 'getMany' && !$argument->isArray()) {
            return [];
        }

        return $this->configs()
            ->filter(fn (array $config): bool => is_string($config['name'] ?? null) && $config['name'] !== '')
            ->map(function (array $config) use ($argument): array {
                $name = $config['name'];
                $item = [
                    'label'    => $name,
                    'kind'     => 12,
                    'textEdit' => [
                        'range'   => $argument->replacementRange(),
                        'newText' => $name,
                    ],
                ];

                $value = $config['value'] ?? null;

                if ($this->hasCompletionDetail($value)) {
                    $item['detail'] = is_scalar($value) ? (string) $value : 'array(...)';
                }

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * Determine if the config value should be shown as completion detail.
     */
    protected function hasCompletionDetail(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== false && $value !== 0;
    }

    /**
     * Find the config for the given argument.
     *
     * @return array<string, mixed>|null
     */
    protected function find(DetectedArgument $argument): ?array
    {
        $value = $argument->stringValue();

        if ($value === null) {
            return null;
        }

        $config = $this->configs()->firstWhere('name', $value);

        return is_array($config) ? $config : null;
    }

    /**
     * Get the available config entries.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function configs(): Collection
    {
        return $this->project->index->configs()['configs'];
    }
}
