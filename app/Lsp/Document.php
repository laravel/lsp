<?php

declare(strict_types=1);

namespace App\Lsp;

use App\Parser\DetectWalker;
use App\Parser\Walker;
use Illuminate\Support\Collection;

class Document
{
    /**
     * The cached detected parser items.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    protected ?Collection $detected = null;

    /**
     * The cached autocomplete results.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $autocomplete = [];

    /**
     * The cached document lines.
     *
     * @var array<int, string>|null
     */
    protected ?array $lines = null;

    /**
     * Create a new document instance.
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $content,
    ) {}

    /**
     * Detect parser items in the document.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function detect(): Collection
    {
        return $this->detected ??= (new DetectWalker($this->content))->walk();
    }

    /**
     * Get the autocomplete result for the document.
     *
     * @param  array<string, mixed>|null  $position
     * @return array<string, mixed>
     */
    public function autocomplete(?array $position = null): array
    {
        $key = $position === null
            ? 'document'
            : implode(':', [
                $position['line'] ?? '',
                $position['character'] ?? '',
            ]);

        return $this->autocomplete[$key] ??= (new Walker(
            $position === null ? $this->content : $this->contentUntil($position)
        ))->walk()->findAutocompleting()?->flip() ?? [];
    }

    /**
     * Get the document lines.
     *
     * @return array<int, string>
     */
    public function lines(): array
    {
        return $this->lines ??= explode("\n", $this->content);
    }

    /**
     * Get a single document line.
     */
    public function line(int $line): string
    {
        return $this->lines()[$line] ?? '';
    }

    /**
     * Get document text inside the given LSP range.
     *
     * @param  array<string, mixed>  $range
     */
    public function textInRange(array $range): string
    {
        $start = $range['start'] ?? null;
        $end = $range['end'] ?? null;

        if (!is_array($start) || !is_array($end)) {
            return '';
        }

        $line = $start['line'] ?? null;
        $endLine = $end['line'] ?? null;
        $character = $start['character'] ?? null;
        $endCharacter = $end['character'] ?? null;

        if (!is_int($line) || !is_int($endLine) || !is_int($character) || !is_int($endCharacter)) {
            return '';
        }

        if ($line !== $endLine) {
            return '';
        }

        return substr(
            $this->line($line),
            $character,
            $endCharacter - $character,
        );
    }

    /**
     * Get document content up to the given position.
     *
     * @param  array<string, mixed>  $position
     */
    protected function contentUntil(array $position): string
    {
        $line = $position['line'] ?? null;
        $character = $position['character'] ?? null;

        if (!is_int($line) || !is_int($character)) {
            return $this->content;
        }

        $before = array_slice($this->lines(), 0, $line);
        $before[] = substr($this->line($line), 0, $character);

        return implode("\n", $before);
    }
}
