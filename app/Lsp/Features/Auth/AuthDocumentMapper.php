<?php

declare(strict_types=1);

namespace App\Lsp\Features\Auth;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\DetectedArguments;
use App\Lsp\Detection\Pattern;
use App\Lsp\Document;
use App\Lsp\Features\Support\DocumentMapper;
use App\Lsp\Project;
use App\Lsp\Support\Position;
use Illuminate\Support\Collection;

class AuthDocumentMapper extends DocumentMapper
{
    /**
     * Create a new auth document mapper instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get auth detection patterns.
     *
     * @return array<int, Pattern>
     */
    protected function patterns(): array
    {
        return [
            Pattern::attribute(class: 'Illuminate\\Routing\\Attributes\\Controllers\\Authorize', argument: 0),
            Pattern::method(method: ['has', 'allows', 'denies', 'check', 'any', 'authorize', 'inspect'], class: Pattern::contract('Auth\\Access\\Gate'), argument: 0),
            Pattern::method(method: ['has', 'allows', 'denies', 'check', 'any', 'none', 'authorize', 'inspect'], class: Pattern::facade('Gate'), argument: 0),
            Pattern::method(method: ['can', 'cannot'], class: [...Pattern::facade('Route'), ...Pattern::facade('Auth')], argument: 0),
            Pattern::method(method: ['@can', '@cannot', '@canany'], argument: 0),
        ];
    }

    /**
     * Get matched auth arguments from the document.
     *
     * @return Collection<int, DetectedArgument>
     */
    public function arguments(Document $document): Collection
    {
        return DetectedArguments::in($document)
            ->matching($this->patterns())
            ->stringsAndArrays()
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
        return collect($argument->stringValues())
            ->map(function (array $value) use ($argument): ?array {
                $policies = $this->matchingPolicies($argument, $value['value']);

                if ($policies->count() !== 1) {
                    return null;
                }

                $policy = $policies->first();

                return $this->project->link($value['range'], $policy['uri'], $policy['line']);
            })
            ->filter()
            ->values()
            ->all();
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

            return $this->hoverForValue($argument, $value);
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
            ->flatMap(fn (array $value): array => $this->diagnosticFrom($argument, $value['value'], $value['range']))
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
        return $this->policies()
            ->filter(fn (array $group): bool => is_string($group['ability'] ?? null) && $group['ability'] !== '')
            ->map(function (array $group) use ($argument): array {
                $ability = $group['ability'];
                $policies = $group['policies'];
                $item = [
                    'label'    => $ability,
                    'kind'     => 12,
                    'textEdit' => [
                        'range'   => $argument->replacementRange(),
                        'newText' => $ability,
                    ],
                ];

                $policyClasses = $policies
                    ->pluck('policy')
                    ->filter(fn (mixed $policy): bool => is_string($policy) && $policy !== '')
                    ->values()
                    ->all();

                if ($policyClasses !== []) {
                    $item['detail'] = implode("\n\n", $policyClasses);
                }

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * Resolve diagnostics for a policy value.
     *
     * @param  array<string, array<string, int>>  $range
     * @return array<int, array<string, mixed>>
     */
    protected function diagnosticFrom(DetectedArgument $argument, string $value, array $range): array
    {
        if ($value === '') {
            return [];
        }

        $policies = $this->policyGroup($value);

        if ($policies->isEmpty()) {
            return [$this->notFound('Policy', $value, $range, 'auth')];
        }

        if (!$this->requiresModel($argument) || $this->modelClass($argument) === null || $this->matchingPolicies($argument, $value)->isNotEmpty()) {
            return [];
        }

        return [$this->notFound('Policy/Model match', $value, $range, 'auth')];
    }

    /**
     * Get matching policies for an ability.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function matchingPolicies(DetectedArgument $argument, string $value): Collection
    {
        $policies = $this->policyGroup($value);

        if (!$this->requiresModel($argument)) {
            return $policies;
        }

        $class = $this->modelClass($argument);

        return $class === null
            ? collect()
            : $policies->filter(fn (array $policy): bool => ($policy['model'] ?? null) === $class)->values();
    }

    /**
     * Determine if the policy call should match a model.
     */
    protected function requiresModel(DetectedArgument $argument): bool
    {
        $item = $argument->item();

        return ($item['type'] ?? null) === 'methodCall'
            && !in_array($item['methodName'] ?? null, ['has'], true)
            && isset($item['arguments']['children'][1]);
    }

    /**
     * Resolve the model class from the second argument.
     */
    protected function modelClass(DetectedArgument $argument): ?string
    {
        $next = $argument->item()['arguments']['children'][1]['children'][0] ?? null;

        if (!is_array($next)) {
            return null;
        }

        if (($next['type'] ?? null) === 'array') {
            $next = $next['children'][0]['value'] ?? null;
        }

        return is_array($next) && is_string($next['className'] ?? null)
            ? $next['className']
            : null;
    }

    /**
     * Get policy groups by ability.
     *
     * @return Collection<int, array{ability: string, policies: Collection<int, array<string, mixed>>}>
     */
    protected function policies(): Collection
    {
        return collect($this->project->index->auth()['policies'] ?? [])
            ->map(fn (array $policies, string $ability): array => [
                'ability'  => $ability,
                'policies' => collect($policies),
            ])
            ->values();
    }

    /**
     * Get the policy group for an ability.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function policyGroup(string $ability): Collection
    {
        $group = $this->policies()->firstWhere('ability', $ability);

        return is_array($group) ? $group['policies'] : collect();
    }

    /**
     * Create a hover response for a detected policy value.
     *
     * @param  array{value: string, range: array<string, array<string, int>>}  $value
     * @return array<string, mixed>|null
     */
    protected function hoverForValue(DetectedArgument $argument, array $value): ?array
    {
        $policies = $this->matchingPolicies($argument, $value['value']);

        if ($policies->isEmpty()) {
            return null;
        }

        $lines = $policies->map(fn (array $policy): string => implode("\n\n", array_filter([
            $policy['policy'] !== null ? "`{$policy['policy']}`" : null,
            "[{$policy['uri']}]({$this->project->target($policy['uri'], $policy['line'])})",
        ])))->values()->all();

        return [
            'range'    => $value['range'],
            'contents' => [
                'kind'  => 'markdown',
                'value' => implode("\n\n", array_values(array_filter($lines))),
            ],
        ];
    }

    /**
     * Create a not-found diagnostic.
     *
     * @param  array<string, array<string, int>>  $range
     * @return array<string, mixed>
     */
    protected function notFound(string $descriptor, string $value, array $range, string $code): array
    {
        return [
            'range'    => $range,
            'severity' => 2,
            'source'   => 'Laravel Extension',
            'code'     => $code,
            'message'  => "{$descriptor} [{$value}] not found.",
        ];
    }
}
