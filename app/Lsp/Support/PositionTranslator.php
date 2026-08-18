<?php

declare(strict_types=1);

namespace App\Lsp\Support;

use App\Lsp\Document;
use App\Lsp\DocumentManager;

/**
 * Converts LSP positions between the negotiated wire encoding and the UTF-8
 * byte offsets every parser, mapper, and provider works in.
 *
 * Translating here rather than inside each feature keeps a single place that
 * has to know about encodings, and keeps the rest of the server on bytes.
 */
class PositionTranslator
{
    /**
     * Create a new position translator instance.
     */
    public function __construct(
        protected DocumentManager $documents,
        protected PositionEncoding $encoding,
    ) {}

    /**
     * Convert wire positions in an incoming payload to byte offsets.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function fromClient(array $payload): array
    {
        return $this->translate($payload, false);
    }

    /**
     * Convert byte offsets in an outgoing payload to wire positions.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function toClient(array $payload, ?Document $document = null): array
    {
        return $this->translate($payload, true, $document);
    }

    /**
     * Resolve the document an outgoing payload belongs to.
     *
     * @param  array<string, mixed>  $payload
     */
    public function documentFor(array $payload): ?Document
    {
        $uri = $payload['textDocument']['uri'] ?? $payload['uri'] ?? null;

        return is_string($uri) ? $this->documents->get($uri) : null;
    }

    /**
     * Translate every position in the payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function translate(array $payload, bool $outgoing, ?Document $document = null): array
    {
        if ($this->encoding === PositionEncoding::Utf8) {
            return $payload;
        }

        /** @var array<string, mixed> $translated */
        $translated = $this->walk($payload, $outgoing, $document);

        return $translated;
    }

    /**
     * Walk a payload value, converting any position it contains.
     */
    protected function walk(mixed $value, bool $outgoing, ?Document $document): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($this->isPosition($value)) {
            return $this->convert($value, $outgoing, $document);
        }

        $document = $this->documentFor($value) ?? $document;

        foreach ($value as $key => $item) {
            // A workspace edit keys its changes by document URI, so positions
            // below one belong to that document rather than the request's.
            $value[$key] = is_string($key) && str_starts_with($key, 'file://')
                ? $this->walk($item, $outgoing, $this->documents->get($key))
                : $this->walk($item, $outgoing, $document);
        }

        return $value;
    }

    /**
     * Determine if the value is an LSP position.
     *
     * @param  array<array-key, mixed>  $value
     */
    protected function isPosition(array $value): bool
    {
        return is_int($value['line'] ?? null) && is_int($value['character'] ?? null);
    }

    /**
     * Convert a single position.
     *
     * @param  array<string, mixed>  $position
     * @return array<string, mixed>
     */
    protected function convert(array $position, bool $outgoing, ?Document $document): array
    {
        /** @var int $line */
        $line = $position['line'];
        /** @var int $character */
        $character = $position['character'];

        // A character offset of zero is identical in every encoding, so a
        // position pointing at the start of a line never needs the document.
        if ($character === 0 || $document === null) {
            return $position;
        }

        $text = $document->line($line);

        $position['character'] = $outgoing
            ? $this->encoding->fromByteOffset($text, $character)
            : $this->encoding->toByteOffset($text, $character);

        return $position;
    }
}
