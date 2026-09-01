<?php

use App\Parser\DetectWalker;

function parsedStringValue(string $literal): ?string
{
    $context = (new DetectWalker("<?php\nf(" . $literal . ');'))->walk();

    $stack = [json_decode($context->toJson(), true)];

    while ($stack !== []) {
        $node = array_pop($stack);

        if (!is_array($node)) {
            continue;
        }

        if (($node['type'] ?? null) === 'string') {
            return $node['value'];
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $stack[] = $child;
            }
        }
    }

    return null;
}

test('a parsed string value matches what php evaluates the literal to', function (string $literal) {
    expect(parsedStringValue($literal))->toBe(eval("return {$literal};"));
})->with([
    // double quoted
    '"plain"',
    '"a\nb"', '"a\tb"', '"a\rb"', '"a\vb"', '"a\eb"', '"a\fb"',
    '"a\\\\b"', '"a\$b"', '"a\"b"', '"a\qb"',
    '"a\x41b"', '"a\x4b"', '"a\101b"', '"a\7b"', '"a\0b"',
    '"a\u{1F600}b"', '"a\u{41}b"',
    '"App\\\\Models\\\\User"', '"C:\\\\path\\\\file"',

    // single quoted
    "'plain'", "'a\\nb'", "'a\\\\b'", "'a\\'b'", "'a\\qb'", "'App\\Models\\User'",

    // heredoc: same as double quoted, except a quote is never escaped
    "<<<EOT\nplain\nEOT",
    "<<<EOT\na\\nb\nEOT",
    "<<<EOT\na\\\"b\nEOT",
    "<<<EOT\na\\\\\\\\b\nEOT",
    "<<<EOT\nl1\nl2\nEOT",

    // nowdoc: nothing is escaped at all
    "<<<'EOT'\nplain\nEOT",
    "<<<'EOT'\na\\nb\nEOT",
    "<<<'EOT'\na\\\\\\\\b\nEOT",
]);
