<?php

use App\Lsp\Detection\DetectedArguments;
use App\Lsp\Detection\Pattern;
use App\Lsp\Document;

test('detected argument ranges point at the argument in the original document', function (string $content) {
    $document = new Document('file:///project/resources/views/welcome.blade.php', $content);

    $arguments = DetectedArguments::in($document)
        ->matching([Pattern::method('route')])
        ->strings();

    expect($arguments)->toHaveCount(1);
    expect($document->textInRange($arguments->first()->range()))->toBe('home');
})->with([
    'php'                            => ["<?php\n\nroute('home');\n"],
    'php with leading blank lines'   => ["\n\n<?php\n\nroute('home');\n"],
    'blade'                          => ["<div>\n    <a href=\"{{ route('home') }}\">Home</a>\n</div>\n"],
    'blade with leading blank line'  => ["\n<div>\n    <a href=\"{{ route('home') }}\">Home</a>\n</div>\n"],
    'blade with leading indentation' => ["    <a href=\"{{ route('home') }}\">Home</a>\n"],
]);
