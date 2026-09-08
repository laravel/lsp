<?php

declare(strict_types=1);

namespace App\Lsp\Features\Views;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\DetectedArguments;
use App\Lsp\Detection\Pattern;
use App\Lsp\Document;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Project;
use App\Lsp\Support\Position;
use Illuminate\Support\Collection;

class ViewDocumentMapper extends DocumentMapper
{
    /**
     * Create a new view document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get view detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(method: 'view', class: Pattern::contract('Routing\\ResponseFactory'), argument: 0),
            Pattern::method(method: 'make', class: Pattern::contract('View\\Factory'), argument: 0),
            Pattern::method(method: ['make', 'first', 'renderEach', 'exists'], class: Pattern::facade('View'), argument: 0),
            Pattern::method(method: ['renderWhen', 'renderUnless'], class: Pattern::facade('View'), argument: 1),
            Pattern::method(method: ['view', 'livewire'], class: Pattern::facade('Route'), argument: 1),
            Pattern::method(method: ['markdown', 'view'], class: 'Illuminate\\Notifications\\Messages\\MailMessage', argument: 0),
            Pattern::attribute(class: 'Illuminate\\Mail\\Mailables\\Content', argument: [0, 3]),
            Pattern::method(method: ['@component', '@each', '@extends', '@include', '@includeIf', 'assertViewIs', 'links', 'markdown', 'view'], argument: 0),
            Pattern::method(method: ['@includeWhen', '@includeUnless'], argument: 1),
            Pattern::method(method: '@includeFirst', argument: 0),
        ];
    }

    /**
     * Get matched view arguments from the document.
     *
     * @return Collection<int, DetectedArgument>
     */
    public function arguments(Document $document): Collection
    {
        return DetectedArguments::in($document)
            ->matching($this->patterns())
            ->stringsAndArrays()
            ->filter(fn (DetectedArgument $argument): bool => $argument->param()['type'] === 'string'
                || ($argument->item()['methodName'] ?? null) === '@includeFirst')
            ->filter(fn (DetectedArgument $argument): bool => $this->shouldAccept($argument))
            ->values();
    }

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toLinks(DetectedArgument $argument): array
    {
        $links = [];

        foreach ($argument->stringValues() as $value) {
            $view = $this->find($value['value']);

            if ($view !== null && is_string($view['path'] ?? null)) {
                $links[] = $this->project->link($value['range'], $view['path']);
            }
        }

        return $links;
    }

    /**
     * Convert the given argument to hover.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    protected function toHover(DetectedArgument $argument, array $position): ?array
    {
        foreach ($argument->stringValues() as $value) {
            if (!Position::inRange($value['range'], $position)) {
                continue;
            }

            $view = $this->find($value['value']);

            if ($view === null || !is_string($view['path'] ?? null)) {
                continue;
            }

            return [
                'range'    => $value['range'],
                'contents' => [
                    'kind'  => 'markdown',
                    'value' => "[{$view['path']}]({$this->project->target($view['path'])})",
                ],
            ];
        }

        return null;
    }

    /**
     * Convert the given argument to diagnostics.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toDiagnostics(DetectedArgument $argument): array
    {
        return collect($argument->literalStringValues())
            ->reject(fn (array $value): bool => $this->find($value['value']) !== null)
            ->map(fn (array $value): array => [
                'range'    => $value['range'],
                'severity' => 2,
                'source'   => 'Laravel Extension',
                'code'     => 'view',
                'message'  => "View [{$value['value']}] not found.",
            ])
            ->values()
            ->all();
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        $views = $this->viewsFor($argument);

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
     * Find the view for the given argument.
     *
     * @return array<string, mixed>|null
     */
    protected function find(string $value): ?array
    {
        $view = $this->views()->firstWhere('key', $value);

        return is_array($view) ? $view : null;
    }

    /**
     * Get the views available for the given autocomplete argument.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function viewsFor(AutocompleteArgument $argument): Collection
    {
        $views = $this->views();
        $item = $argument->item();
        $class = $item['className'];

        if ($class === 'Route' || $class === 'Illuminate\\Support\\Facades\\Route') {
            if ($item['methodName'] !== 'livewire') {
                return $views;
            }

            return $views->filter(fn (array $view): bool => ($view['livewire'] ?? null) !== null);
        }

        return $views;
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
