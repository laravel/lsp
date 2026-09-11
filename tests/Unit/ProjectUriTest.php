<?php

declare(strict_types=1);

use App\Lsp\Methods\Initialize;
use App\Lsp\Support\FileUri;

function projectPathFor(string $basePath): string
{
    return Initialize::projectUri(FileUri::fromPath('/workspace'), $basePath)->path();
}

it('uses the workspace root when no base path is configured', function () {
    expect(projectPathFor(''))->toBe('/workspace')
        ->and(projectPathFor('   '))->toBe('/workspace')
        ->and(projectPathFor('.'))->toBe('/workspace');
});

it('resolves the project against the workspace root', function () {
    expect(projectPathFor('api'))->toBe('/workspace/api')
        ->and(projectPathFor('apps/api'))->toBe('/workspace/apps/api');
});

it('ignores separators around the base path', function () {
    expect(projectPathFor('/api/'))->toBe('/workspace/api')
        ->and(projectPathFor(' api '))->toBe('/workspace/api')
        ->and(projectPathFor('apps\\api'))->toBe('/workspace/apps/api');
});

it('keeps the base path inside the workspace', function () {
    // The base path names a directory in the workspace, so an absolute one is
    // read as relative rather than pointing the server somewhere else.
    expect(projectPathFor('/etc'))->toBe('/workspace/etc');
});
