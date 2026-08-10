<?php

declare(strict_types=1);

use App\Lsp\DocumentManager;
use App\Lsp\Methods\TextDocumentFormatting;
use App\Lsp\PintRunner;
use App\Lsp\Project;
use App\Lsp\ProjectIndex;
use App\Lsp\ScriptRunner;
use App\Lsp\Support\FileUri;
use App\Lsp\Transport\JsonRpcRequest;
use Illuminate\Container\Container;

function formatting(DocumentManager $documents): TextDocumentFormatting
{
    $uri = FileUri::fromPath(base_path());

    return new TextDocumentFormatting($documents, new Project(
        $uri,
        [],
        new ProjectIndex(new Container),
        new ScriptRunner($uri->path(), ['php']),
        new PintRunner($uri->path(), ['php']),
    ));
}

function formattingRequest(string $uri): JsonRpcRequest
{
    return JsonRpcRequest::from([
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'textDocument/formatting',
        'params'  => ['textDocument' => ['uri' => $uri]],
    ]);
}

it('returns a whole document edit for an unformatted document', function () {
    $uri = (string) FileUri::fromPath(base_path('tests/Unit/Unformatted.php'));

    $documents = new DocumentManager;
    $documents->open($uri, "<?php\n\nclass  Foo{\npublic function bar( ){return   1;}\n}\n");

    $response = formatting($documents)->handle(formattingRequest($uri));

    $edits = json_decode($response->toJson(), true)['result'];

    expect($edits)->toHaveCount(1)
        ->and($edits[0]['range']['start'])->toBe(['line' => 0, 'character' => 0])
        ->and($edits[0]['range']['end'])->toBe(['line' => 5, 'character' => 0])
        ->and($edits[0]['newText'])->toContain('public function bar()');
});

it('returns no edits for an already formatted document', function () {
    $uri = (string) FileUri::fromPath(base_path('tests/Unit/Formatted.php'));

    $documents = new DocumentManager;
    $documents->open($uri, "<?php\n\nclass Foo\n{\n    //\n}\n");

    $response = formatting($documents)->handle(formattingRequest($uri));

    expect(json_decode($response->toJson(), true)['result'])->toBeNull();
});

it('returns no edits for a document that is not open', function () {
    $response = formatting(new DocumentManager)->handle(
        formattingRequest((string) FileUri::fromPath(base_path('tests/Unit/Missing.php'))),
    );

    expect(json_decode($response->toJson(), true)['result'])->toBeNull();
});

it('spans the whole document, counting utf-16 code units', function () {
    expect(TextDocumentFormatting::range("<?php\n"))
        ->toBe([
            'start' => ['line' => 0, 'character' => 0],
            'end'   => ['line' => 1, 'character' => 0],
        ]);

    expect(TextDocumentFormatting::range("<?php\n// \u{1F418}"))
        ->toBe([
            'start' => ['line' => 0, 'character' => 0],
            'end'   => ['line' => 1, 'character' => 5],
        ]);
});
