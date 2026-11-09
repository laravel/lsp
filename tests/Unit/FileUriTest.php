<?php

use App\Lsp\Support\FileUri;

test('relative path for unix paths', function () {
    $uri = FileUri::of('file:///home/runner/work/project');

    expect($uri->relativePath('/home/runner/work/project/app/Models/User.php'))
        ->toBe('app/Models/User.php');
});

test('relative path for windows backslash paths with uppercase drive letter', function () {
    $uri = FileUri::of('file:///d%3A/a/project/project');

    expect($uri->relativePath('D:\\a\\project\\project\\app\\Models\\User.php'))
        ->toBe('app/Models/User.php');
});

test('relative path for windows paths with mixed separators', function () {
    $uri = FileUri::of('file:///d%3A/a/project/project');

    expect($uri->relativePath('d:/a/project\\project\\app/Models/User.php'))
        ->toBe('app/Models/User.php');
});

test('relative path for windows paths with mismatched drive letter case', function () {
    $uri = FileUri::of('file:///D%3A/a/project/project');

    expect($uri->relativePath('d:\\a\\project\\project\\app\\Models\\User.php'))
        ->toBe('app/Models/User.php');
});

test('relative path returns the original path when outside the base path', function () {
    $uri = FileUri::of('file:///home/runner/work/project');

    expect($uri->relativePath('/tmp/other/file.php'))->toBe('/tmp/other/file.php');
});

test('relative path does not match a sibling directory sharing the base path prefix', function () {
    $uri = FileUri::of('file:///home/runner/work/project');

    expect($uri->relativePath('/home/runner/work/project-two/file.php'))
        ->toBe('/home/runner/work/project-two/file.php');
});

test('relative path returns an empty string for the base path itself', function () {
    $uri = FileUri::of('file:///d%3A/a/project/project');

    expect($uri->relativePath('D:\\a\\project\\project'))->toBe('');
});

test('relative path for a project root reached through a symlink', function () {
    $real = sys_get_temp_dir() . '/lsp-symlink-real-' . getmypid();
    $link = sys_get_temp_dir() . '/lsp-symlink-' . getmypid();

    mkdir($real . '/config', 0777, true);
    touch($real . '/config/app.php');

    if (!@symlink($real, $link)) {
        $this->markTestSkipped('Unable to create a symlink on this platform.');
    }

    try {
        expect(FileUri::fromPath($link)->relativePath($link . '/config/app.php'))
            ->toBe('config/app.php');
    } finally {
        @unlink($link);
        @unlink($real . '/config/app.php');
        @rmdir($real . '/config');
        @rmdir($real);
    }
});

test('relative path when the root is a symlink and the client sends the resolved path', function () {
    $real = sys_get_temp_dir() . '/lsp-mirror-real-' . getmypid();
    $link = sys_get_temp_dir() . '/lsp-mirror-link-' . getmypid();

    mkdir($real . '/config', 0777, true);
    touch($real . '/config/app.php');

    if (!@symlink($real, $link)) {
        $this->markTestSkipped('Unable to create a symlink on this platform.');
    }

    try {
        $uri = FileUri::fromPath($link);

        expect($uri->relativePath($real . '/config/app.php'))->toBe('config/app.php');
        expect($uri->relativePath($real))->toBe('');
    } finally {
        @unlink($link);
        @unlink($real . '/config/app.php');
        @rmdir($real . '/config');
        @rmdir($real);
    }
});

test('relative path leaves a file outside the project untouched', function () {
    $root = sys_get_temp_dir() . '/lsp-outside-root-' . getmypid();
    $outside = sys_get_temp_dir() . '/lsp-outside-other-' . getmypid();

    mkdir($root, 0777, true);
    mkdir($outside, 0777, true);
    touch($outside . '/x.php');

    try {
        expect(FileUri::fromPath($root)->relativePath($outside . '/x.php'))
            ->toBe($outside . '/x.php');
    } finally {
        @unlink($outside . '/x.php');
        @rmdir($outside);
        @rmdir($root);
    }
});
