<?php

declare(strict_types=1);

namespace App\Lsp\Features\Storage;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\Pattern;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class StorageDocumentMapper extends DocumentMapper
{
    /**
     * Create a new storage document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get storage detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::attribute(class: Pattern::containerAttribute('Storage'), argument: 0),
            Pattern::method(method: ['disk', 'fake', 'persistentFake', 'forgetDisk'], class: Pattern::facade('Storage'), argument: 0),
        ];
    }

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toLinks(DetectedArgument $argument): array
    {
        $disk = $this->find($argument);

        if ($disk === null || !is_string($disk['file'] ?? null)) {
            return [];
        }

        return [
            $this->project->link(
                $argument->range(),
                $disk['file'],
                is_numeric($disk['line'] ?? null) ? (int) $disk['line'] : null,
            ),
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
            'code'     => 'storage_disk',
            'message'  => "Storage Disk [{$value}] not found.",
        ]];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        return $this->disks()
            ->filter(fn (array $disk): bool => is_string($disk['disk'] ?? null) && $disk['disk'] !== '' && !str_contains($disk['disk'], '.'))
            ->map(fn (array $disk): array => [
                'label'    => $disk['disk'],
                'kind'     => 12,
                'textEdit' => [
                    'range'   => $argument->replacementRange(),
                    'newText' => $disk['disk'],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Find the storage disk for the given argument.
     *
     * @return array<string, mixed>|null
     */
    protected function find(DetectedArgument $argument): ?array
    {
        $value = $argument->stringValue();

        if ($value === null) {
            return null;
        }

        $disk = $this->disks()->firstWhere('disk', $value);

        return is_array($disk) ? $disk : null;
    }

    /**
     * Get the available storage disk configs.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function disks(): Collection
    {
        return $this->project->index->configs()['configs']
            ->filter(fn (array $config): bool => str_starts_with((string) ($config['name'] ?? ''), 'filesystems.disks.'))
            ->map(fn (array $config): array => [
                'disk' => str_replace('filesystems.disks.', '', (string) $config['name']),
                ...$config,
            ])
            ->values();
    }
}
