<?php

declare(strict_types=1);

namespace App\Lsp\Features\Inertia;

use App\Lsp\CodeActions\CodeActionContext;
use App\Lsp\Contracts\CodeActionProvider;
use App\Lsp\Document;
use App\Lsp\Support\FileUri;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class InertiaCodeActionProvider implements CodeActionProvider
{
    /**
     * Create a new Inertia code action provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide Inertia code actions for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, CodeActionContext $context): array
    {
        if (!$context->accepts('quickfix')) {
            return [];
        }

        return $context->diagnostics('inertia')
            ->flatMap(fn (array $diagnostic): array => $this->actionsFor($document, $diagnostic))
            ->values()
            ->all();
    }

    /**
     * Get code actions for the given diagnostic.
     *
     * @param  array<string, mixed>  $diagnostic
     * @return array<int, array<string, mixed>>
     */
    protected function actionsFor(Document $document, array $diagnostic): array
    {
        $missing = $document->textInRange($diagnostic['range']);

        if ($missing === '') {
            return [];
        }

        $extension = $this->extension();

        return $this->pagePaths()
            ->map(fn (string $path): array => $this->createPageAction($path, $missing, $extension, $diagnostic))
            ->values()
            ->all();
    }

    /**
     * Create the Inertia page code action.
     *
     * @param  array<string, mixed>  $diagnostic
     * @return array<string, mixed>
     */
    protected function createPageAction(string $path, string $missing, string $extension, array $diagnostic): array
    {
        $relativePath = trim($path, '/') . '/' . ltrim($missing, '/') . ".{$extension}";
        $uri = (string) FileUri::fromPath($this->project->path($relativePath));
        $contents = $this->contents($extension);

        return [
            'title'       => "Create {$relativePath}",
            'kind'        => 'quickfix',
            'diagnostics' => [$diagnostic],
            'isPreferred' => true,
            'edit'        => [
                'documentChanges' => $this->documentChanges($uri, $contents),
            ],
            'command' => [
                'title'     => 'Open file',
                'command'   => 'laravel.open',
                'arguments' => [$uri, 1, 0],
            ],
        ];
    }

    /**
     * Get document changes for creating the page.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function documentChanges(string $uri, string $contents): array
    {
        $changes = [[
            'kind'    => 'create',
            'uri'     => $uri,
            'options' => [
                'overwrite' => false,
            ],
        ]];

        if ($contents === '') {
            return $changes;
        }

        $changes[] = [
            'textDocument' => [
                'uri'     => $uri,
                'version' => null,
            ],
            'edits' => [[
                'range' => [
                    'start' => [
                        'line'      => 0,
                        'character' => 0,
                    ],
                    'end' => [
                        'line'      => 0,
                        'character' => 0,
                    ],
                ],
                'newText' => $contents,
            ]],
        ];

        return $changes;
    }

    /**
     * Get the page extension to create.
     */
    protected function extension(): string
    {
        $existing = $this->views()
            ->pluck('path')
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => pathinfo($path, PATHINFO_EXTENSION))
            ->first(fn (string $extension): bool => $extension !== '');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $configured = $this->pageExtensions()->first();

        return is_string($configured) && $configured !== '' ? $configured : 'vue';
    }

    /**
     * Get configured Inertia page paths.
     *
     * @return Collection<int, string>
     */
    protected function pagePaths(): Collection
    {
        return $this->project->index->inertiaViews()['page_paths'];
    }

    /**
     * Get configured Inertia page extensions.
     *
     * @return Collection<int, string>
     */
    protected function pageExtensions(): Collection
    {
        return $this->project->index->inertiaViews()['page_extensions'];
    }

    /**
     * Get the available Inertia views.
     *
     * @return Collection<string, array<string, string>>
     */
    protected function views(): Collection
    {
        return $this->project->index->inertiaViews()['views'];
    }

    /**
     * Get initial page contents for the extension.
     */
    protected function contents(string $extension): string
    {
        return match ($extension) {
            'vue'   => "<script setup>\n\n</script>\n\n<template>\n\n</template>",
            default => '',
        };
    }
}
