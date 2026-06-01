<?php

declare(strict_types=1);

namespace App\Lsp\Features\Routes;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\Pattern;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class RouteDocumentMapper extends DocumentMapper
{
    /**
     * Create a new route document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get route detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::attribute(
                class: 'Illuminate\\Foundation\\Http\\Attributes\\RedirectToRoute',
                argument: 0,
            ),
            Pattern::method(
                method: ['route', 'signedRoute', 'to_route', 'temporarySignedRoute', 'redirectToRoute'],
                argument: 0,
            ),
            Pattern::method(
                method: ['route', 'signedRoute', 'to_route', 'temporarySignedRoute', 'redirectToRoute'],
                class: [
                    'Redirect',
                    'URL',
                    'Response',
                    'redirect',
                    'url',
                    'Illuminate\\Support\\Facades\\Redirect',
                    'Illuminate\\Support\\Facades\\URL',
                    'Illuminate\\Support\\Facades\\Response',
                    'Illuminate\\Routing\\UrlGenerator',
                    'Illuminate\\Routing\\ResponseFactory',
                ],
                argument: 0,
            ),
            Pattern::method(
                method: 'route',
                class: 'Livewire\\Volt\\Volt',
                argument: 1,
            ),
            Pattern::method(
                method: 'is',
                class: [
                    'Route',
                    'Illuminate\\Support\\Facades\\Route',
                    'Illuminate\\Routing\\Router',
                ],
                argument: 0,
            ),
            Pattern::method(
                method: 'routeIs',
                class: [
                    'Request',
                    'Illuminate\\Support\\Facades\\Request',
                    'Illuminate\\Http\\Request',
                ],
                argument: 0,
            ),
        ];
    }

    /**
     * Determine if the given argument should be accepted.
     */
    protected function shouldAccept(DetectedArgument $argument): bool
    {
        $value = $argument->stringValue();

        return $value !== null && !str_contains($value, '*');
    }

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toLinks(DetectedArgument $argument): array
    {
        $value = $argument->stringValue();

        if ($value === null) {
            return [];
        }

        if ($this->isVoltArgument($argument)) {
            $view = $this->voltView($value);

            if ($view === null || !is_string($view['path'] ?? null)) {
                return [];
            }

            return [[
                'range'  => $argument->range(),
                'target' => $this->project->target($view['path']),
            ]];
        }

        $route = $this->find($argument);

        if ($route === null || !is_string($route['filename'] ?? null)) {
            return [];
        }

        return [[
            'range'  => $argument->range(),
            'target' => $this->project->target($route['filename'], (int) ($route['line'] ?? 1)),
        ]];
    }

    /**
     * Convert the given argument to hover.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    protected function toHover(DetectedArgument $argument, array $position): ?array
    {
        $route = $this->find($argument);

        if ($route === null || !is_string($route['filename'] ?? null)) {
            return null;
        }

        $action = ($route['action'] ?? null) === 'Closure'
            ? '[Closure]'
            : (string) ($route['action'] ?? '');
        $filename = $route['filename'];
        $target = $this->project->target($filename);

        return [
            'range'    => $argument->range(),
            'contents' => [
                'kind'  => 'markdown',
                'value' => implode("\n\n", [
                    $action,
                    "[{$filename}]({$target})",
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

        if ($value === null) {
            return [];
        }

        $descriptor = 'Route';
        $exists = $this->find($argument) !== null;

        if ($this->isVoltArgument($argument)) {
            $descriptor = 'Component';
            $exists = $this->voltView($value) !== null;
        }

        if ($exists) {
            return [];
        }

        return [[
            'range'    => $argument->range(),
            'severity' => 2,
            'source'   => 'Laravel Extension',
            'code'     => 'route',
            'message'  => "{$descriptor} [{$value}] not found.",
        ]];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        return $this->routes()
            ->filter(fn (array $route): bool => is_string($route['name'] ?? null) && $route['name'] !== '')
            ->map(fn (array $route): array => [
                'label'    => $route['name'],
                'kind'     => 13,
                'detail'   => $this->completionDetail($route),
                'textEdit' => [
                    'range'   => $argument->replacementRange(),
                    'newText' => $route['name'],
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Find the route for the given argument.
     *
     * @return array<string, mixed>|null
     */
    protected function find(DetectedArgument $argument): ?array
    {
        $value = $argument->stringValue();

        if ($value === null) {
            return null;
        }

        $route = $this->routes()->firstWhere('name', $value);

        return is_array($route) ? $route : null;
    }

    /**
     * Get the available routes.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function routes(): Collection
    {
        return $this->project->index->routes();
    }

    /**
     * Get the completion detail for the given route.
     *
     * @param  array<string, mixed>  $route
     */
    protected function completionDetail(array $route): string
    {
        return implode("\n\n", [
            (string) ($route['action'] ?? ''),
            '[' . (string) ($route['method'] ?? '') . '] ' . (string) ($route['uri'] ?? ''),
        ]);
    }

    /**
     * Determine if the argument belongs to a Volt route call.
     */
    protected function isVoltArgument(DetectedArgument $argument): bool
    {
        return ($argument->item()['className'] ?? null) === 'Livewire\\Volt\\Volt'
            && ($argument->item()['methodName'] ?? null) === 'route';
    }

    /**
     * Get the view backing a Volt route component.
     *
     * @return array<string, mixed>|null
     */
    protected function voltView(string $component): ?array
    {
        $view = $this->project->index->views()
            ->firstWhere('key', "livewire.{$component}");

        return is_array($view) ? $view : null;
    }
}
