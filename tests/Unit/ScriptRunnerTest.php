<?php

use App\Lsp\ScriptRunner;

function scriptRunnerProject(string $suffix): string
{
    $root = sys_get_temp_dir() . '/lsp-runner-' . getmypid() . '-' . $suffix;

    @mkdir($root . '/vendor', 0777, true);
    @mkdir($root . '/bootstrap', 0777, true);
    touch($root . '/vendor/autoload.php');
    touch($root . '/bootstrap/app.php');

    return $root;
}

function scriptRunnerFor(string $root): ScriptRunner
{
    return new class($root, ['php']) extends ScriptRunner
    {
        public function generate(string $code): string
        {
            return $this->code($code);
        }
    };
}

function scriptRunnerCleanup(string ...$paths): void
{
    foreach ($paths as $path) {
        if (is_link($path)) {
            @unlink($path);

            continue;
        }

        if (!is_dir($path)) {
            @unlink($path);

            continue;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                scriptRunnerCleanup($path . '/' . $entry);
            }
        }

        @rmdir($path);
    }
}

test('the bootstrap targets the project root even when storage is a symlink', function () {
    $root = scriptRunnerProject('symlinked-storage');
    $shared = sys_get_temp_dir() . '/lsp-shared-' . getmypid();

    @mkdir($shared . '/framework', 0777, true);

    if (!@symlink($shared, $root . '/storage')) {
        scriptRunnerCleanup($root, $shared);

        $this->markTestSkipped('Unable to create a symlink on this platform.');
    }

    try {
        $base = realpath($root);

        expect(scriptRunnerFor($root)->generate('<?php //'))
            ->toContain("require '" . $base . "/vendor/autoload.php';")
            ->toContain("require '" . $base . "/bootstrap/app.php';");
    } finally {
        scriptRunnerCleanup($root, $shared);
    }
});

test('the bootstrap escapes a project path containing a quote', function () {
    $root = scriptRunnerProject("it's");

    try {
        $generated = scriptRunnerFor($root)->generate('<?php //');

        expect($generated)->toContain(var_export(realpath($root) . '/vendor/autoload.php', true));

        $script = sys_get_temp_dir() . '/lsp-lint-' . getmypid() . '.php';
        file_put_contents($script, $generated);
        exec('php -l ' . escapeshellarg($script) . ' 2>&1', $output, $status);
        @unlink($script);

        expect($status)->toBe(0);
    } finally {
        scriptRunnerCleanup($root);
    }
});

test('the bootstrap is empty when the project cannot be booted without artisan', function () {
    $root = sys_get_temp_dir() . '/lsp-runner-' . getmypid() . '-bare';

    @mkdir($root, 0777, true);

    try {
        expect(scriptRunnerFor($root)->generate('<?php //'))
            ->not->toContain('LARAVEL_START');
    } finally {
        scriptRunnerCleanup($root);
    }
});
