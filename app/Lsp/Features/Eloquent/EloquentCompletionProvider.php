<?php

declare(strict_types=1);

namespace App\Lsp\Features\Eloquent;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Support\FileUri;
use App\Lsp\Workspace;
use Illuminate\Support\Collection;

class EloquentCompletionProvider implements CompletionProvider
{
    /**
     * Eloquent relation-aware methods.
     *
     * @var array<int, string>
     */
    protected array $relationMethods = [
        'doesntHave',
        'doesntHaveMorph',
        'has',
        'hasMorph',
        'orDoesntHave',
        'orDoesntHaveMorph',
        'orHas',
        'orHasMorph',
        'orWhereDoesntHave',
        'orWhereDoesntHaveMorph',
        'orWhereHas',
        'orWhereHasMorph',
        'whereDoesntHave',
        'whereDoesntHaveMorph',
        'whereHas',
        'whereHasMorph',
        'with',
        'withAggregate',
        'withAvg',
        'withCount',
        'withMax',
        'withMin',
        'withSum',
    ];

    /**
     * Eloquent methods that complete attributes in the first parameter.
     *
     * @var array<int, string>
     */
    protected array $firstParamMethods = [
        'create',
        'fill',
        'firstWhere',
        'make',
        'max',
        'orderBy',
        'orderByDesc',
        'orWhere',
        'select',
        'sum',
        'update',
        'where',
        'whereColumn',
        'whereIn',
    ];

    /**
     * Eloquent methods that complete attributes in any parameter after the first.
     *
     * @var array<int, string>
     */
    protected array $anyParamMethods = [
        'createOrFirst',
        'firstOrNew',
        'firstOrCreate',
        'updateOrCreate',
    ];

    /**
     * Eloquent model attribute classes.
     *
     * @var array<int, string>
     */
    protected array $attributeClasses = [
        'Illuminate\\Database\\Eloquent\\Attributes\\Fillable',
        'Illuminate\\Database\\Eloquent\\Attributes\\Guarded',
        'Illuminate\\Database\\Eloquent\\Attributes\\Hidden',
        'Illuminate\\Database\\Eloquent\\Attributes\\Visible',
        'Illuminate\\Database\\Eloquent\\Attributes\\Appends',
    ];

    /**
     * Create a new Eloquent completion provider instance.
     */
    public function __construct(
        protected Workspace $workspace,
    ) {}

    /**
     * Provide Eloquent completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        $item = $document->autocomplete($position);

        if ($item === [] || !$this->mightComplete($item)) {
            return [];
        }

        $models = collect($this->workspace->data->models()->get());

        if ($models->isEmpty()) {
            return [];
        }

        $class = $this->modelClass($item, $document, $models);

        if ($class === null) {
            return [];
        }

        $model = $models->get($class);

        if (!is_array($model)) {
            return [];
        }

        $method = $this->methodName($item);

        if ($method === null) {
            if ($this->isInsideAttributeObject($item) && $this->isFillingInArrayValue($item)) {
                return $this->completionItems($this->attributeNames($model), $document, $position);
            }

            if ($this->isCurrentArgumentArray($item) && $this->isFillingInArrayKey($item)) {
                return $this->completionItems($this->fillableAttributeNames($model, $item), $document, $position);
            }

            return [];
        }

        if (in_array($method, $this->anyParamMethods, true)) {
            if ($this->argumentIndex($item) === 0) {
                return $this->completionItems($this->queryAttributeNames($model), $document, $position);
            }

            return $this->completionItems($this->fillableAttributeNames($model, $item), $document, $position);
        }

        if (in_array($method, $this->firstParamMethods, true)) {
            if (($this->argumentIndex($item) ?? 0) > 0) {
                return [];
            }

            if (in_array($method, ['create', 'make', 'fill'], true)) {
                if ($this->isCurrentArgumentArray($item) && $this->isFillingInArrayKey($item)) {
                    return $this->completionItems($this->fillableAttributeNames($model, $item), $document, $position);
                }

                return [];
            }

            return $this->completionItems($this->queryAttributeNames($model), $document, $position);
        }

        if (in_array($method, $this->relationMethods, true)) {
            if (($this->argumentIndex($item) ?? 0) > 0) {
                return [];
            }

            if (!$this->isCurrentArgumentArray($item) || $this->isFillingInArrayKey($item)) {
                return $this->completionItems($this->relationNames($model, $item), $document, $position, 12);
            }

            $relation = $this->relationForCurrentArrayValue($item, $model);

            if (!is_array($relation)) {
                return [];
            }

            $related = $models->get((string) $relation['related']);

            return is_array($related)
                ? $this->completionItems($this->queryAttributeNames($related), $document, $position)
                : [];
        }

        return [];
    }

    /**
     * Determine if the autocomplete item could be handled by this provider.
     *
     * @param  array<string, mixed>  $item
     */
    protected function mightComplete(array $item): bool
    {
        $method = $this->methodName($item);

        if ($method !== null) {
            return in_array($method, $this->methods(), true);
        }

        return $this->className($item) !== null || $this->isInsideAttributeObject($item);
    }

    /**
     * Resolve the model class for the current autocomplete item.
     *
     * @param  array<string, mixed>  $item
     * @param  Collection<string, array<string, mixed>>  $models
     */
    protected function modelClass(array $item, Document $document, Collection $models): ?string
    {
        if ($this->isFillingInArrayValue($item) && $this->isInsideAttributeObject($item)) {
            return $this->modelClassForDocument($document, $models);
        }

        $method = $this->methodName($item);

        if ($method === null) {
            $class = $this->className($item);

            return is_string($class) && $models->has($class) ? $class : null;
        }

        if (!in_array($method, $this->methods(), true)) {
            return null;
        }

        foreach ($this->contexts($item) as $context) {
            $class = $this->className($context);

            if (!is_string($class) || $class === 'Illuminate\\Database\\Eloquent\\Builder') {
                continue;
            }

            if (!$models->has($class)) {
                return null;
            }

            $contextMethod = $this->methodName($context);

            if ($contextMethod === null || !in_array($contextMethod, $this->relationMethods, true)) {
                return $class;
            }

            $related = $this->relatedModelClass($context, $models->get($class));

            if ($related !== null) {
                return $related;
            }

            return null;
        }

        return null;
    }

    /**
     * Resolve the model class for the current document path.
     *
     * @param  Collection<string, array<string, mixed>>  $models
     */
    protected function modelClassForDocument(Document $document, Collection $models): ?string
    {
        $path = str_replace('\\', '/', $this->workspace->uri()->relativePath(FileUri::of($document->uri)->path()));

        return $models
            ->first(fn (array $model): bool => str_replace('\\', '/', (string) ($model['path'] ?? '')) === $path)['class'] ?? null;
    }

    /**
     * Get all Eloquent methods handled by this provider.
     *
     * @return array<int, string>
     */
    protected function methods(): array
    {
        return [
            ...$this->relationMethods,
            ...$this->firstParamMethods,
            ...$this->anyParamMethods,
        ];
    }

    /**
     * Get attribute names for model attribute classes.
     *
     * @param  array<string, mixed>  $model
     * @return array<int, string>
     */
    protected function attributeNames(array $model): array
    {
        return collect($model['attributes'] ?? [])
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();
    }

    /**
     * Get queryable attribute names for a model.
     *
     * @param  array<string, mixed>  $model
     * @return array<int, string>
     */
    protected function queryAttributeNames(array $model): array
    {
        return collect($model['attributes'] ?? [])
            ->filter(fn (mixed $attribute): bool => is_array($attribute))
            ->filter(fn (array $attribute): bool => !in_array((string) ($attribute['cast'] ?? ''), ['accessor', 'attribute'], true))
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();
    }

    /**
     * Get fillable attribute names for a model.
     *
     * @param  array<string, mixed>  $model
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    protected function fillableAttributeNames(array $model, array $item): array
    {
        $existing = $this->currentArrayKeys($item);

        return collect($model['attributes'] ?? [])
            ->filter(fn (mixed $attribute): bool => is_array($attribute) && ($attribute['fillable'] ?? false) === true)
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '' && !in_array($name, $existing, true))
            ->values()
            ->all();
    }

    /**
     * Get relation names for a model.
     *
     * @param  array<string, mixed>  $model
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    protected function relationNames(array $model, array $item): array
    {
        $existing = $this->currentArrayKeys($item);

        return collect($model['relations'] ?? [])
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '' && !in_array($name, $existing, true))
            ->values()
            ->all();
    }

    /**
     * Get the related model class for a relation method context.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $model
     */
    protected function relatedModelClass(array $context, ?array $model): ?string
    {
        if ($model === null) {
            return null;
        }

        $relation = $this->relationForCurrentArrayValue($context, $model);

        return is_array($relation) && is_string($relation['related'] ?? null)
            ? $relation['related']
            : null;
    }

    /**
     * Get the current relation while completing an array value.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $model
     * @return array<string, mixed>|null
     */
    protected function relationForCurrentArrayValue(array $item, array $model): ?array
    {
        $relationName = $this->currentRelationArrayKey($item);

        if ($relationName === null) {
            return null;
        }

        $relation = collect($model['relations'] ?? [])
            ->first(fn (mixed $relation): bool => is_array($relation) && ($relation['name'] ?? null) === $relationName);

        return is_array($relation) ? $relation : null;
    }

    /**
     * Get the relation key for the current array value completion.
     *
     * @param  array<string, mixed>  $item
     */
    protected function currentRelationArrayKey(array $item): ?string
    {
        $argument = $this->currentArgument($item);

        if (!is_array($argument) || ($argument['type'] ?? null) !== 'array') {
            return null;
        }

        $children = array_reverse(array_values(is_array($argument['children'] ?? null) ? $argument['children'] : []));

        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            $key = $child['key']['value'] ?? null;

            if (is_string($key) && (($child['autocompletingValue'] ?? false) === true || $key !== '')) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Determine if the item is inside an Eloquent attribute object.
     *
     * @param  array<string, mixed>  $item
     */
    protected function isInsideAttributeObject(array $item): bool
    {
        foreach ($this->contexts($item) as $context) {
            if (($context['type'] ?? null) !== 'object') {
                continue;
            }

            if (in_array($this->className($context), $this->attributeClasses, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current argument node.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    protected function currentArgument(array $item): ?array
    {
        if (($item['type'] ?? null) === 'array') {
            return $item;
        }

        $index = $this->argumentIndex($item);

        if ($index === null) {
            return null;
        }

        $argument = $this->arguments($item)[$index] ?? null;

        if (!is_array($argument)) {
            return null;
        }

        $child = $argument['children'][0] ?? null;

        if (is_array($child)) {
            return $child;
        }

        return ($argument['type'] ?? null) !== 'argument' ? $argument : null;
    }

    /**
     * Determine if the current argument is an array.
     *
     * @param  array<string, mixed>  $item
     */
    protected function isCurrentArgumentArray(array $item): bool
    {
        return ($this->currentArgument($item)['type'] ?? null) === 'array';
    }

    /**
     * Determine if the current autocomplete context is filling an array key.
     *
     * @param  array<string, mixed>  $item
     */
    protected function isFillingInArrayKey(array $item): bool
    {
        if (($item['type'] ?? null) === 'array') {
            return ($item['autocompletingKey'] ?? false) === true;
        }

        return ($this->currentArgument($item)['autocompletingKey'] ?? false) === true;
    }

    /**
     * Determine if the current autocomplete context is filling an array value.
     *
     * @param  array<string, mixed>  $item
     */
    protected function isFillingInArrayValue(array $item): bool
    {
        if (($item['type'] ?? null) === 'array') {
            return ($item['autocompletingValue'] ?? false) === true;
        }

        return ($this->currentArgument($item)['autocompletingValue'] ?? false) === true;
    }

    /**
     * Get string keys from the current array argument.
     *
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    protected function currentArrayKeys(array $item): array
    {
        $argument = $this->currentArgument($item);

        if (!is_array($argument) || ($argument['type'] ?? null) !== 'array') {
            return [];
        }

        return collect($argument['children'] ?? [])
            ->map(fn (mixed $child): mixed => is_array($child) ? ($child['key']['value'] ?? null) : null)
            ->filter(fn (mixed $key): bool => is_string($key))
            ->values()
            ->all();
    }

    /**
     * Get the class name from a parser item.
     *
     * @param  array<string, mixed>  $item
     */
    protected function className(array $item): ?string
    {
        $class = $item['className'] ?? $item['class'] ?? null;

        return is_string($class) ? $class : null;
    }

    /**
     * Get the method name from a parser item.
     *
     * @param  array<string, mixed>  $item
     */
    protected function methodName(array $item): ?string
    {
        $method = $item['methodName'] ?? $item['name'] ?? null;

        return is_string($method) ? $method : null;
    }

    /**
     * Get the current autocompleting argument index.
     *
     * @param  array<string, mixed>  $item
     */
    protected function argumentIndex(array $item): ?int
    {
        $index = $item['arguments']['autocompletingIndex'] ?? $item['paramIndex'] ?? null;

        return is_int($index) ? $index : null;
    }

    /**
     * Get argument nodes from a parser item.
     *
     * @param  array<string, mixed>  $item
     * @return array<int, array<string, mixed>>
     */
    protected function arguments(array $item): array
    {
        $arguments = $item['arguments']['children'] ?? $item['arguments'] ?? [];

        return is_array($arguments) ? array_values(array_filter($arguments, 'is_array')) : [];
    }

    /**
     * Walk the current parser item and its parents.
     *
     * @param  array<string, mixed>  $item
     * @return array<int, array<string, mixed>>
     */
    protected function contexts(array $item): array
    {
        $contexts = [];
        $context = $item;

        while ($context !== []) {
            $contexts[] = $context;

            $parent = $context['parent'] ?? null;
            $context = is_array($parent) ? $parent : [];
        }

        return $contexts;
    }

    /**
     * Create completion items for the given labels.
     *
     * @param  array<int, string>  $labels
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    protected function completionItems(array $labels, Document $document, array $position, int $kind = 5): array
    {
        return collect($labels)
            ->unique()
            ->map(fn (string $label): array => [
                'label'    => $label,
                'kind'     => $kind,
                'textEdit' => [
                    'range'   => $this->replacementRange($document, $position),
                    'newText' => $label,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * Get the range that should be replaced by the completion.
     *
     * @param  array<string, mixed>  $position
     * @return array{start: array{line: int, character: int}, end: array{line: int, character: int}}
     */
    protected function replacementRange(Document $document, array $position): array
    {
        $line = (int) $position['line'];
        $character = (int) $position['character'];
        $text = substr(explode("\n", $document->content)[$line] ?? '', 0, $character);

        preg_match('/[\\w\\d\\-_\\.\\:\\\\\/@]+$/', $text, $matches);

        $start = $character - strlen($matches[0] ?? '');

        return [
            'start' => [
                'line'      => $line,
                'character' => max(0, $start),
            ],
            'end' => [
                'line'      => $line,
                'character' => $character,
            ],
        ];
    }
}
