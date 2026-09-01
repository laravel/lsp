<?php

use App\Lsp\Document;
use App\Lsp\DocumentManager;
use App\Lsp\Support\PositionEncoding;
use App\Lsp\Support\PositionTranslator;

test('negotiates the encoding a client offers', function () {
    expect(PositionEncoding::negotiate(['utf-16', 'utf-8']))->toBe(PositionEncoding::Utf8)
        ->and(PositionEncoding::negotiate(['utf-16']))->toBe(PositionEncoding::Utf16)
        ->and(PositionEncoding::negotiate(['utf-32']))->toBe(PositionEncoding::Utf32);
});

test('defaults to utf-16 when a client advertises nothing', function () {
    expect(PositionEncoding::negotiate([]))->toBe(PositionEncoding::Utf16)
        ->and(PositionEncoding::negotiate(['utf-64']))->toBe(PositionEncoding::Utf16);
});

test('converts byte offsets to utf-16 code units', function (string $line, int $bytes, int $expected) {
    expect(PositionEncoding::Utf16->fromByteOffset($line, $bytes))->toBe($expected);
})->with([
    ["view('x');", 6, 6],
    ["\$x = '日本語'; view('x');", 24, 18],
    ["\$x = '🎉'; view('x');", 19, 17],
]);

test('converts utf-16 code units back to byte offsets', function (string $line, int $bytes, int $units) {
    expect(PositionEncoding::Utf16->toByteOffset($line, $units))->toBe($bytes);
})->with([
    ["view('x');", 6, 6],
    ["\$x = '日本語'; view('x');", 24, 18],
    ["\$x = '🎉'; view('x');", 19, 17],
]);

test('counts an astral character as two utf-16 code units', function () {
    expect(PositionEncoding::Utf16->fromByteOffset('🎉', 4))->toBe(2)
        ->and(PositionEncoding::Utf32->fromByteOffset('🎉', 4))->toBe(1)
        ->and(PositionEncoding::Utf8->fromByteOffset('🎉', 4))->toBe(4);
});

test('does not split a character an offset lands inside', function () {
    // Offset 1 is halfway through the surrogate pair, so it clamps to the start.
    expect(PositionEncoding::Utf16->toByteOffset('🎉x', 1))->toBe(0)
        ->and(PositionEncoding::Utf16->toByteOffset('🎉x', 2))->toBe(4)
        ->and(PositionEncoding::Utf16->toByteOffset('🎉x', 3))->toBe(5);
});

test('clamps offsets past the end of a line', function () {
    expect(PositionEncoding::Utf16->fromByteOffset('日', 99))->toBe(1)
        ->and(PositionEncoding::Utf16->toByteOffset('日', 99))->toBe(3)
        ->and(PositionEncoding::Utf16->fromByteOffset('日', -5))->toBe(0);
});

test('leaves invalid utf-8 bytes addressable', function () {
    $line = "a\xFFb";

    expect(PositionEncoding::Utf16->fromByteOffset($line, 3))->toBe(3)
        ->and(PositionEncoding::Utf16->toByteOffset($line, 3))->toBe(3);
});

function translator(PositionEncoding $encoding, array $documents = []): PositionTranslator
{
    $manager = new DocumentManager;

    foreach ($documents as $uri => $content) {
        $manager->open($uri, $content);
    }

    return new PositionTranslator($manager, $encoding);
}

test('converts an incoming position to a byte offset', function () {
    $uri = 'file:///project/app/Http/Controllers/HomeController.php';

    $translated = translator(PositionEncoding::Utf16, [
        $uri => "<?php\n\$x = '日本語'; view('x');",
    ])->fromClient([
        'textDocument' => ['uri' => $uri],
        'position'     => ['line' => 1, 'character' => 18],
    ]);

    expect($translated['position'])->toBe(['line' => 1, 'character' => 24]);
});

test('converts an outgoing diagnostic range to the wire encoding', function () {
    $uri = 'file:///project/app/Http/Controllers/HomeController.php';

    $translated = translator(PositionEncoding::Utf16, [
        $uri => "<?php\n\$x = '日本語'; view('x');",
    ])->toClient([
        'uri'         => $uri,
        'diagnostics' => [[
            'range' => [
                'start' => ['line' => 1, 'character' => 24],
                'end'   => ['line' => 1, 'character' => 27],
            ],
            'message' => 'View not found.',
        ]],
    ]);

    expect($translated['diagnostics'][0]['range'])->toBe([
        'start' => ['line' => 1, 'character' => 18],
        'end'   => ['line' => 1, 'character' => 21],
    ]);
});

test('leaves positions alone when utf-8 is negotiated', function () {
    $uri = 'file:///project/app.php';

    $payload = [
        'textDocument' => ['uri' => $uri],
        'position'     => ['line' => 1, 'character' => 24],
    ];

    expect(translator(PositionEncoding::Utf8, [$uri => "<?php\n\$x = '日本語';"])->fromClient($payload))
        ->toBe($payload);
});

test('leaves positions alone for a document that is not open', function () {
    $payload = [
        'uri'         => 'file:///project/.env',
        'diagnostics' => [[
            'range' => [
                'start' => ['line' => 0, 'character' => 12],
                'end'   => ['line' => 0, 'character' => 20],
            ],
        ]],
    ];

    expect(translator(PositionEncoding::Utf16)->toClient($payload))->toBe($payload);
});

test('resolves workspace edit positions against the document each change targets', function () {
    $current = 'file:///project/resources/views/home.blade.php';
    $other = 'file:///project/app/Other.php';

    $translated = translator(PositionEncoding::Utf16, [
        $current => "<?php\n// 日本語 padding here",
        $other   => "<?php\n\$x = '日本語'; view('x');",
    ])->toClient([
        'edit' => [
            'changes' => [
                $other => [[
                    'range' => [
                        'start' => ['line' => 1, 'character' => 24],
                        'end'   => ['line' => 1, 'character' => 24],
                    ],
                    'newText' => 'x',
                ]],
            ],
        ],
    ], (new Document($current, "<?php\n// 日本語 padding here")));

    expect($translated['edit']['changes'][$other][0]['range']['start'])
        ->toBe(['line' => 1, 'character' => 18]);
});

test('leaves a character offset of zero untouched', function () {
    $payload = [
        'uri'   => 'file:///project/.env',
        'range' => [
            'start' => ['line' => 4, 'character' => 0],
            'end'   => ['line' => 4, 'character' => 0],
        ],
    ];

    expect(translator(PositionEncoding::Utf16)->toClient($payload))->toBe($payload);
});
