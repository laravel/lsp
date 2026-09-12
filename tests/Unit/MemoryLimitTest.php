<?php

use App\Lsp\Project;
use App\Lsp\ProjectIndex;
use App\Lsp\ScriptRunner;
use App\Lsp\Support\FileUri;
use Illuminate\Container\Container;

function memoryLimitProject(array $init = []): Project
{
    return new Project(
        FileUri::of('file:///tmp/laravel-lsp-project'),
        $init,
        new ProjectIndex(new Container),
        new ScriptRunner('/tmp/laravel-lsp-project', ['php']),
    );
}

test('memory limit defaults to 512M', function () {
    expect(memoryLimitProject()->memoryLimit())->toBe('512M');
});

test('memory limit uses the configured value', function (string $limit) {
    expect(memoryLimitProject(['memoryLimit' => $limit])->memoryLimit())->toBe($limit);
})->with(['512M', '256M', '1G', '1024', '-1']);

test('memory limit falls back when the value is invalid', function (mixed $limit) {
    expect(memoryLimitProject(['memoryLimit' => $limit])->memoryLimit())->toBe('512M');
})->with(['lots', '', '512MB', '1.5G', true, false]);

test('applying the memory limit updates the process', function () {
    $previous = ini_get('memory_limit');

    try {
        expect(memoryLimitProject(['memoryLimit' => '256M'])->applyMemoryLimit())->toBe('256M');
        expect(ini_get('memory_limit'))->toBe('256M');
    } finally {
        ini_set('memory_limit', $previous);
    }
});
