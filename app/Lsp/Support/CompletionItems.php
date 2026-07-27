<?php

declare(strict_types=1);

namespace App\Lsp\Support;

use App\Lsp\Document;

class CompletionItems
{
    /**
     * Filter completion items using the complete text in their replacement ranges.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function matching(Document $document, array $items): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => self::matches($document, $item))
            ->values()
            ->all();
    }

    /**
     * Determine if the completion item matches the text it will replace.
     *
     * @param  array<string, mixed>  $item
     */
    protected static function matches(Document $document, array $item): bool
    {
        $textEdit = $item['textEdit'] ?? null;

        if (!is_array($textEdit)) {
            return true;
        }

        $range = $textEdit['range'] ?? null;

        if (!is_array($range)) {
            return true;
        }

        $prefix = $document->textInRange($range);

        if ($prefix === '') {
            return true;
        }

        $filterText = $item['filterText'] ?? $item['label'] ?? null;

        return !is_string($filterText)
            || strncasecmp($filterText, $prefix, strlen($prefix)) === 0;
    }
}
