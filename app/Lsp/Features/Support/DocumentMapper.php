<?php

declare(strict_types=1);

namespace App\Lsp\Features\Support;

use App\Lsp\Detection\AutocompleteArgument;
use App\Lsp\Detection\AutocompleteArguments;
use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Detection\DetectedArguments;
use App\Lsp\Detection\Pattern;
use App\Lsp\Document;
use App\Lsp\Support\Position;
use Illuminate\Support\Collection;

abstract class DocumentMapper
{
    /**
     * Get detection patterns.
     *
     * @return array<int, Pattern>
     */
    abstract protected function patterns(): array;

    /**
     * Convert the given argument to document links.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract protected function toLinks(DetectedArgument $argument): array;

    /**
     * Convert the given argument to hover.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    protected function toHover(DetectedArgument $argument, array $position): ?array
    {
        return null;
    }

    /**
     * Convert the given argument to diagnostics.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toDiagnostics(DetectedArgument $argument): array
    {
        return [];
    }

    /**
     * Convert the given argument to completion items.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function toCompletions(AutocompleteArgument $argument): array
    {
        return [];
    }

    /**
     * Get matched arguments from the document.
     *
     * @return Collection<int, DetectedArgument>
     */
    public function arguments(Document $document): Collection
    {
        return DetectedArguments::in($document)
            ->matching($this->patterns())
            ->strings()
            ->filter(fn (DetectedArgument $argument): bool => $this->shouldAccept($argument))
            ->values();
    }

    /**
     * Get matched autocomplete arguments from the document.
     *
     * @param  array<string, mixed>  $position
     * @return Collection<int, AutocompleteArgument>
     */
    public function completionArguments(Document $document, array $position): Collection
    {
        return AutocompleteArguments::in($document, $position)
            ->matching($this->patterns())
            ->values();
    }

    /**
     * Get the first matched argument at the given position.
     *
     * @param  array<string, mixed>  $position
     */
    public function firstAt(Document $document, array $position): ?DetectedArgument
    {
        return $this->arguments($document)
            ->first(fn (DetectedArgument $argument): bool => $argument->containsPosition($position));
    }

    /**
     * Get the symbol at the given position.
     *
     * @param  array<string, mixed>  $position
     */
    public function symbolAt(Document $document, array $position): ?string
    {
        foreach ($this->arguments($document) as $argument) {
            foreach ($argument->stringValues() as $value) {
                if (Position::inRange($value['range'], $position)) {
                    return $value['value'];
                }
            }
        }

        return null;
    }

    /**
     * Get the ranges of every detected string matching the given symbol.
     *
     * @return array<int, array<string, array<string, int>>>
     */
    public function rangesFor(Document $document, string $symbol): array
    {
        $ranges = [];

        foreach ($this->arguments($document) as $argument) {
            foreach ($argument->stringValues() as $value) {
                if ($value['value'] === $symbol) {
                    $ranges[] = $value['range'];
                }
            }
        }

        return $ranges;
    }

    /**
     * Get document links.
     *
     * @return array<int, array<string, mixed>>
     */
    public function links(Document $document): array
    {
        return $this->arguments($document)
            ->flatMap(fn (DetectedArgument $argument): array => $this->toLinks($argument))
            ->values()
            ->all();
    }

    /**
     * Get completions.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function completions(Document $document, array $position): array
    {
        return $this->completionArguments($document, $position)
            ->flatMap(fn (AutocompleteArgument $argument): array => $this->toCompletions($argument))
            ->values()
            ->all();
    }

    /**
     * Get diagnostics.
     *
     * @return array<int, array<string, mixed>>
     */
    public function diagnostics(Document $document): array
    {
        return $this->arguments($document)
            ->flatMap(fn (DetectedArgument $argument): array => $this->toDiagnostics($argument))
            ->values()
            ->all();
    }

    /**
     * Get hover markdown for the given position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>|null
     */
    public function hover(Document $document, array $position): ?array
    {
        $argument = $this->firstAt($document, $position);

        if ($argument === null) {
            return null;
        }

        return $this->toHover($argument, $position);
    }

    /**
     * Determine if the detected argument should be included.
     */
    protected function shouldAccept(DetectedArgument $argument): bool
    {
        return true;
    }
}
