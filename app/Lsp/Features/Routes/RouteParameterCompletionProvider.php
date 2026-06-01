<?php

declare(strict_types=1);

namespace App\Lsp\Features\Routes;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\AutocompleteArguments;
use App\Lsp\Detection\Pattern;
use App\Lsp\Document;
use App\Lsp\Project;
use Illuminate\Support\Collection;

class RouteParameterCompletionProvider implements CompletionProvider
{
    /**
     * Create a new route parameter completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide route parameter completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!$this->project->boolean('routeCompletion', true)) {
            return [];
        }

        $routes = $this->project->index->routes()->keyBy('name');

        return AutocompleteArguments::in($document, $position)
            ->matching($this->patterns())
            ->values()
            ->filter(fn (AutocompleteArgument $argument): bool => $argument->isArray())
            ->flatMap(fn (AutocompleteArgument $argument): array => $this->toCompletions($argument, $routes))
            ->values()
            ->all();
    }

    /**
     * Get route parameter completion patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::method(
                method: ['route', 'signedRoute', 'to_route', 'temporarySignedRoute'],
                argument: 1,
            ),
            Pattern::method(
                method: ['route', 'signedRoute', 'temporarySignedRoute'],
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
                argument: 1,
            ),
        ];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @param  Collection<string, array<string, mixed>>  $routes
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument, Collection $routes): array
    {
        $routeName = $argument->stringValueAt($argument->argumentIndex() - 1);

        if ($routeName === null) {
            return [];
        }

        $route = $routes->get($routeName);

        if (!is_array($route)) {
            return [];
        }

        return collect($route['parameters'] ?? [])
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
}
