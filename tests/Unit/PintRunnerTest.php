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

it('enables the blade rule for templates', function () {
    $runner = new PintRunner('/app', ['php']);

    expect($runner->command('/app/resources/views/home.blade.php'))->toContain('--blade')
        ->and($runner->command('/app/routes/web.php'))->not->toContain('--blade');
});

it('resolves a configured pint path', function () {
    $relative = new PintRunner('/app', ['php'], 'tools/pint/vendor/bin/pint');
    $absolute = new PintRunner('/app', ['php'], '/opt/pint/pint');
    $blank = new PintRunner('/app', ['php'], '  ');

    expect($relative->binary())->toBe('/app' . DIRECTORY_SEPARATOR . 'tools/pint/vendor/bin/pint')
        ->and($absolute->binary())->toBe('/opt/pint/pint')
        ->and($blank->binary())->toBe('/app' . DIRECTORY_SEPARATOR . 'vendor/bin/pint');
});

it('expands a home directory in a configured pint path', function () {
    $home = getenv('HOME') ?: getenv('USERPROFILE');

    expect((new PintRunner('/app', ['php'], '~/.composer/vendor/bin/pint'))->binary())
        ->toBe($home . '/.composer/vendor/bin/pint')
        ->and((new PintRunner('/app', ['php'], 'tilde~inside/pint'))->binary())
        ->toBe('/app' . DIRECTORY_SEPARATOR . 'tilde~inside/pint');
})->skip(!getenv('HOME') && !getenv('USERPROFILE'), 'Requires a home directory.');

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

it('declines to format when pint discards the document name', function () {
    // The suite pins laravel/pint below 1.30.5, which is the release that
    // started naming the stdin file after the document. Formatting through
    // an older Pint with psr_autoloading enabled would rename the class.
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

    expect($formatted)->toBeNull();
})->skip(PHP_OS_FAMILY === 'Windows', 'Requires symlink support.');

it('applies exclusions when the project root is reached through a symlink', function () {
    $real = sys_get_temp_dir() . '/laravel-lsp-real-' . bin2hex(random_bytes(6));
    $link = sys_get_temp_dir() . '/laravel-lsp-link-' . bin2hex(random_bytes(6));

    mkdir($real . '/ignored', 0700, true);
    symlink(base_path('vendor'), $real . '/vendor');
    file_put_contents($real . '/pint.json', json_encode([
        'preset'  => 'laravel',
        'exclude' => ['ignored'],
    ]));

    if (!@symlink($real, $link)) {
        $this->markTestSkipped('Unable to create a symlink on this platform.');
    }

    $contents = "<?php\n\nclass  Foo{}\n";

    expect((new PintRunner($link, ['php']))->format($link . '/ignored/Foo.php', $contents))
        ->toBe($contents);
})->skip(PHP_OS_FAMILY === 'Windows', 'Requires symlink support.');

it('leaves excluded paths untouched', function () {
    $runner = new PintRunner(base_path(), ['php']);

    $contents = "<?php\nclass  Foo{}\n";

    expect($runner->format(base_path('builds/excluded.php'), $contents))->toBe($contents);
});
