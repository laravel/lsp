<?php

use App\Lsp\Document;
use App\Lsp\Features\Views\ViewDocumentMapper;
use App\Lsp\Project;
use App\Lsp\ProjectIndex;
use App\Lsp\ScriptRunner;
use App\Lsp\Support\FileUri;
use Illuminate\Support\Collection;

function viewMapper(): ViewDocumentMapper
{
    $index = new class extends ProjectIndex
    {
        public function __construct() {}

        public function views(): Collection
        {
            return collect([
                ['key' => 'components.alert', 'path' => 'resources/views/components/alert.blade.php'],
                ['key' => 'layouts.app', 'path' => 'resources/views/layouts/app.blade.php'],
                ['key' => 'partials.default', 'path' => 'resources/views/partials/default.blade.php'],
                ['key' => 'partials.first', 'path' => 'resources/views/partials/first.blade.php'],
                ['key' => 'partials.if', 'path' => 'resources/views/partials/if.blade.php'],
                ['key' => 'partials.item', 'path' => 'resources/views/partials/item.blade.php'],
                ['key' => 'partials.unless', 'path' => 'resources/views/partials/unless.blade.php'],
                ['key' => 'partials.when', 'path' => 'resources/views/partials/when.blade.php'],
            ]);
        }
    };

    return new ViewDocumentMapper(new Project(
        uri: FileUri::of('file:///project'),
        init: [],
        index: $index,
        scripts: new ScriptRunner('/project', []),
    ));
}

test('links all Blade directives that reference views', function () {
    $mapper = viewMapper();
    $document = new Document('file:///project/resources/views/page.blade.php', <<<'BLADE'
@component('components.alert')
@extends('layouts.app')
@include('partials.default')
@includeIf('partials.if')
@includeWhen($condition, 'partials.when')
@includeUnless($condition, 'partials.unless')
@includeFirst(['partials.first', 'partials.default'])
@each('partials.item', $items, 'item')
BLADE);

    $links = $mapper->links($document);

    expect($links)->toHaveCount(9);
    expect(array_map(fn (array $link): string => (string) $link['target'], $links))
        ->each->toStartWith('file:///project/resources/views/');
});

test('reports missing views in includeFirst', function () {
    $mapper = viewMapper();
    $document = new Document(
        'file:///project/resources/views/page.blade.php',
        "@includeFirst(['partials.first', 'partials.missing'])",
    );

    expect($mapper->diagnostics($document))->toBe([[
        'range'    => [
            'start' => ['line' => 0, 'character' => 34],
            'end'   => ['line' => 0, 'character' => 50],
        ],
        'severity' => 2,
        'source'   => 'Laravel Extension',
        'code'     => 'view',
        'message'  => 'View [partials.missing] not found.',
    ]]);
});
