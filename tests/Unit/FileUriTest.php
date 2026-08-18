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

test('contains a path inside the project root', function () {
    $uri = FileUri::of('file:///home/runner/work/project');

    expect($uri->contains('/home/runner/work/project/storage/framework/testing/_pest.php'))
        ->toBeTrue();
});

test('contains the project root itself', function () {
    $uri = FileUri::of('file:///home/runner/work/project');

    expect($uri->contains('/home/runner/work/project'))->toBeTrue();
});

test('does not contain a path that traverses out of the project root', function () {
    $uri = FileUri::of('file:///home/runner/work/project');

    expect($uri->contains('/home/runner/work/project/../../tmp/evil_pest.php'))
        ->toBeFalse();
});

test('does not contain a sibling directory sharing the project root prefix', function () {
    $uri = FileUri::of('file:///home/runner/work/project');

    expect($uri->contains('/home/runner/work/project-two/_pest.php'))->toBeFalse();
});

test('contains a path that traverses out of and back into the project root', function () {
    $uri = FileUri::of('file:///home/runner/work/project');

    expect($uri->contains('/home/runner/work/project/tests/../storage/_pest.php'))
        ->toBeTrue();
});

test('resolve removes traversal segments from a path that does not exist', function () {
    $temp = FileUri::resolve(sys_get_temp_dir());
    $missing = $temp . '/lsp-' . bin2hex(random_bytes(4));

    expect(FileUri::resolve($missing . '/project/../../tmp/evil_pest.php'))
        ->toBe($temp . '/tmp/evil_pest.php');
});

test('resolve follows symlinked ancestors', function () {
    $base = sys_get_temp_dir() . '/lsp-' . bin2hex(random_bytes(4));
    $target = $base . '/target';
    $link = $base . '/link';

    mkdir($target, 0777, true);
    symlink($target, $link);

    expect(FileUri::resolve($link . '/_pest.php'))
        ->toBe(FileUri::resolve($target) . '/_pest.php');

    unlink($link);
    rmdir($target);
    rmdir($base);
})->skipOnWindows();
