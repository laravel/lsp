<?php

use App\Lsp\Project;
use App\Lsp\ProjectIndex;
use App\Lsp\ScriptRunner;
use App\Lsp\Support\FileUri;
use App\Lsp\Watchers\PestHelperWatcher;
use Illuminate\Container\Container;

function pestHelperFilePath(array $options): string
{
    $uri = FileUri::of('file:///home/runner/work/project');

    $watcher = new class(new Project($uri, $options, new ProjectIndex(new Container), new ScriptRunner($uri->path(), ['php']))) extends PestHelperWatcher
    {
        public function path(): string
        {
            return $this->helperFilePath();
        }
    };

    return $watcher->path();
}

test('uses the default path when none is configured', function () {
    expect(pestHelperFilePath([]))
        ->toBe('/home/runner/work/project/storage/framework/testing/_pest.php');
});

test('uses a configured path inside the project root', function () {
    expect(pestHelperFilePath(['pestHelperFilePath' => 'storage/_pest.php']))
        ->toBe('/home/runner/work/project/storage/_pest.php');
});

test('falls back to the default path when the configured path escapes the project root', function () {
    expect(pestHelperFilePath(['pestHelperFilePath' => '../../tmp/evil_pest.php']))
        ->toBe('/home/runner/work/project/storage/framework/testing/_pest.php');
});

test('falls back to the default path for a deep traversal chain', function () {
    expect(pestHelperFilePath(['pestHelperFilePath' => 'storage/../../../../../etc/evil_pest.php']))
        ->toBe('/home/runner/work/project/storage/framework/testing/_pest.php');
});

test('falls back to the default path when the configured path is not a string', function () {
    expect(pestHelperFilePath(['pestHelperFilePath' => ['storage/_pest.php']]))
        ->toBe('/home/runner/work/project/storage/framework/testing/_pest.php');
});

test('falls back to the default path when the configured path is blank', function () {
    expect(pestHelperFilePath(['pestHelperFilePath' => '   ']))
        ->toBe('/home/runner/work/project/storage/framework/testing/_pest.php');
});
