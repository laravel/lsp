<?php

declare(strict_types=1);

use App\Lsp\PintRunner;

it('builds a stdin command for php documents', function () {
    $runner = new PintRunner('/app', ['php']);

    expect($runner->command('/app/routes/web.php'))->toBe([
        'php',
        '/app' . DIRECTORY_SEPARATOR . 'vendor/bin/pint',
        '--quiet',
        '--no-interaction',
        '--stdin-filename',
        '/app/routes/web.php',
    ]);
});

it('enables the blade rule for templates however pint reads them', function () {
    $runner = new PintRunner('/app', ['php']);

    expect($runner->fileCommand('/tmp/x/home.blade.php'))->toContain('--blade')
        ->and($runner->fileCommand('/tmp/x/User.php'))->not->toContain('--blade')
        ->and($runner->command('/app/resources/views/home.blade.php'))->toContain('--blade')
        ->and($runner->command('/app/routes/web.php'))->not->toContain('--blade');
});

it('detects that pint discards the document name when reading stdin', function () {
    // The suite pins laravel/pint below the release that keeps the name.
    expect((new PintRunner(base_path(), ['php']))->keepsDocumentName())->toBeFalse();
});

it('preserves the detected php command', function () {
    $runner = new PintRunner('/app', ['docker', 'compose', 'exec', 'app', 'php']);

    expect(array_slice($runner->command('/app/routes/web.php'), 0, 5))
        ->toBe(['docker', 'compose', 'exec', 'app', 'php']);
});

it('reports pint as unavailable when it is not installed', function () {
    expect((new PintRunner('/does-not-exist', ['php']))->available())->toBeFalse()
        ->and((new PintRunner(base_path(), ['php']))->available())->toBeTrue();
});

it('formats a document without touching the file system', function () {
    $runner = new PintRunner(base_path(), ['php']);

    $path = base_path('tests/Unit/UnwrittenFixture.php');

    $formatted = $runner->format($path, "<?php\n\nclass  Foo{\npublic function bar( ){return   1;}\n}\n");

    expect($formatted)->toContain('class Foo')
        ->and($formatted)->toContain('public function bar()')
        ->and(file_exists($path))->toBeFalse();
});

it('returns null when pint is not installed', function () {
    $runner = new PintRunner('/does-not-exist', ['php']);

    expect($runner->format('/does-not-exist/a.php', '<?php $a=1;'))->toBeNull();
});

it('detects a document rewritten to match pint\'s temporary stdin file', function () {
    expect(PintRunner::leaksTempFileName(
        "<?php\n\nclass Foo {}\n",
        "<?php\n\nclass pint_stdin_6a7929d20a373\n{\n}\n",
    ))->toBeTrue();

    expect(PintRunner::leaksTempFileName(
        "<?php\n\nclass Foo {}\n",
        "<?php\n\nclass Foo\n{\n}\n",
    ))->toBeFalse();

    expect(PintRunner::leaksTempFileName(
        "<?php\n\n\$name = 'pint_stdin_example';\n",
        "<?php\n\n\$name = 'pint_stdin_example';\n",
    ))->toBeFalse();
});

it('formats through a real file name when psr_autoloading is enabled', function () {
    $project = sys_get_temp_dir() . '/laravel-lsp-psr-' . bin2hex(random_bytes(6));

    mkdir($project, 0700, true);
    symlink(base_path('vendor'), $project . '/vendor');
    file_put_contents($project . '/pint.json', json_encode([
        'preset' => 'laravel',
        'rules'  => ['psr_autoloading' => true],
    ]));

    $formatted = (new PintRunner($project, ['php']))->format(
        $project . '/app/Models/User.php',
        "<?php\n\nnamespace App\\Models;\n\nclass  User{\npublic function bar( ){return   1;}\n}\n",
    );

    expect($formatted)->toContain('class User')
        ->and($formatted)->not->toContain('pint_stdin_')
        ->and($formatted)->toContain('namespace App\\Models;')
        ->and($formatted)->toContain('public function bar()');
})->skip(PHP_OS_FAMILY === 'Windows', 'Requires symlink support.');

it('leaves excluded paths untouched', function () {
    $runner = new PintRunner(base_path(), ['php']);

    $contents = "<?php\nclass  Foo{}\n";

    expect($runner->format(base_path('builds/excluded.php'), $contents))->toBe($contents);
});
