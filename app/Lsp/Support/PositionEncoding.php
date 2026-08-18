<?php

declare(strict_types=1);

namespace App\Lsp\Support;

/**
 * The offset unit used for the "character" member of an LSP position.
 *
 * The parser works in UTF-8 byte offsets. The protocol defaults to UTF-16 code
 * units, so unless a client negotiates something else every position has to be
 * converted on the way in and on the way out.
 */
enum PositionEncoding: string
{
    case Utf8 = 'utf-8';
    case Utf16 = 'utf-16';
    case Utf32 = 'utf-32';

    /**
     * Pick an encoding from the encodings a client supports.
     *
     * UTF-8 is preferred when offered because it matches the parser and makes
     * conversion a no-op. UTF-16 is the protocol default and the fallback for a
     * client that advertises nothing.
     *
     * @param  array<int, mixed>  $supported
     */
    public static function negotiate(array $supported): self
    {
        foreach ([self::Utf8, self::Utf16, self::Utf32] as $encoding) {
            if (in_array($encoding->value, $supported, true)) {
                return $encoding;
            }
        }

        return self::Utf16;
    }

    /**
     * Convert a byte offset within a line to an offset in this encoding.
     */
    public function fromByteOffset(string $line, int $byteOffset): int
    {
        if ($this === self::Utf8) {
            return $byteOffset;
        }

        $byteOffset = max(0, min($byteOffset, strlen($line)));
        $offset = 0;

        for ($index = 0; $index < $byteOffset; $index++) {
            $byte = ord($line[$index]);

            if ($this->isContinuation($byte)) {
                continue;
            }

            $offset += $this->unitsFor($byte);
        }

        return $offset;
    }

    /**
     * Convert an offset in this encoding to a byte offset within a line.
     */
    public function toByteOffset(string $line, int $offset): int
    {
        if ($this === self::Utf8) {
            return $offset;
        }

        $length = strlen($line);
        $index = 0;
        $consumed = 0;

        while ($index < $length && $consumed < $offset) {
            $byte = ord($line[$index]);
            $units = $this->unitsFor($byte);

            // Stop short rather than split a character the offset lands inside.
            if ($consumed + $units > $offset) {
                break;
            }

            $consumed += $units;
            $index += $this->widthFor($byte);
        }

        return min($index, $length);
    }

    /**
     * Determine if the byte is a UTF-8 continuation byte.
     */
    protected function isContinuation(int $byte): bool
    {
        return ($byte & 0xC0) === 0x80;
    }

    /**
     * Get the number of offset units the character starting with the byte uses.
     */
    protected function unitsFor(int $byte): int
    {
        // A four byte sequence is outside the basic multilingual plane, so it is
        // a surrogate pair in UTF-16 and a single code point everywhere else.
        return $this === self::Utf16 && $byte >= 0xF0 ? 2 : 1;
    }

    /**
     * Get the byte width of the character starting with the byte.
     */
    protected function widthFor(int $byte): int
    {
        return match (true) {
            $byte >= 0xF0 => 4,
            $byte >= 0xE0 => 3,
            $byte >= 0xC0 => 2,
            default       => 1,
        };
    }
}
