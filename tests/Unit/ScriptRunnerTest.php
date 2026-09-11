<?php

use App\Lsp\ScriptRunner;
use Symfony\Component\Process\Process;

function scriptRunnerProject(string $suffix): string
{
    $root = sys_get_temp_dir() . '/lsp-runner-' . getmypid() . '-' . $suffix;

    @mkdir($root . '/vendor', 0777, true);
    @mkdir($root . '/bootstrap', 0777, true);
    file_put_contents($root . '/vendor/autoload.php', '<?php define("SCRIPT_RUNNER_PROJECT", dirname(__DIR__));');
    file_put_contents($root . '/bootstrap/app.php', <<<'PHP'
    <?php

    return new class
    {
        public function make(string $abstract): object
        {
            return new class
            {
                public function bootstrap(): void
                {
                    define('SCRIPT_RUNNER_BOOTED', true);
                }
            };
        }
    };
    PHP);

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
        expect(scriptRunnerFor($root)->json('echo json_encode([SCRIPT_RUNNER_PROJECT, SCRIPT_RUNNER_BOOTED]);'))
            ->toBe([realpath($root), true]);
    } finally {
        scriptRunnerCleanup($root, $shared);
    }
});

test('the bootstrap supports a project path containing a quote', function () {
    $root = scriptRunnerProject("it's");

    try {
        expect(scriptRunnerFor($root)->json('echo json_encode([SCRIPT_RUNNER_PROJECT, SCRIPT_RUNNER_BOOTED]);'))
            ->toBe([realpath($root), true]);
    } finally {
        scriptRunnerCleanup($root);
    }
});

test('the bootstrap resolves the project inside the PHP environment', function () {
    $host = scriptRunnerProject('host');
    $runtime = scriptRunnerProject('runtime');

    try {
        mkdir($runtime . '/storage/framework', 0777, true);

        $script = 'storage/framework/lsp.php';
        file_put_contents($runtime . '/' . $script, scriptRunnerFor($host)->generate(
            'echo json_encode([SCRIPT_RUNNER_PROJECT, SCRIPT_RUNNER_BOOTED]);'
        ));

        $process = new Process([PHP_BINARY, $script], $runtime);
        $process->mustRun();

        expect(json_decode($process->getOutput(), true))->toBe([realpath($runtime), true]);
    } finally {
        scriptRunnerCleanup($host, $runtime);
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
