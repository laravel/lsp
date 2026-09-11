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

test('a batch runs every script in a single process', function () {
    $root = scriptRunnerProject('batch');

    try {
        expect(scriptRunnerFor($root)->batch([
            'first'  => 'define("SCRIPT_RUNNER_FIRST", true); echo json_encode(SCRIPT_RUNNER_BOOTED);',
            'second' => '<?php echo json_encode(defined("SCRIPT_RUNNER_FIRST"));',
            'third'  => 'use Random\Randomizer; echo json_encode((new Randomizer)->getInt(3, 3));',
        ]))->toBe(['first' => true, 'second' => true, 'third' => 3]);
    } finally {
        scriptRunnerCleanup($root);
    }
});

test('a batch keeps the variables of each script to itself', function () {
    $root = scriptRunnerProject('batch-scope');

    try {
        expect(scriptRunnerFor($root)->batch([
            'first'  => '$shared = 1; echo json_encode($shared);',
            'second' => 'echo json_encode(isset($shared));',
        ]))->toBe(['first' => 1, 'second' => false]);
    } finally {
        scriptRunnerCleanup($root);
    }
});

test('a batch reports a script that throws without affecting the others', function () {
    $root = scriptRunnerProject('batch-throws');

    try {
        expect(scriptRunnerFor($root)->batch([
            'first'  => 'define("SCRIPT_RUNNER_FIRST", true); echo json_encode(1);',
            'second' => 'echo "partial"; throw new RuntimeException("boom");',
            'third'  => 'echo json_encode(defined("SCRIPT_RUNNER_FIRST"));',
        ]))->toBe(['first' => 1, 'second' => null, 'third' => true]);
    } finally {
        scriptRunnerCleanup($root);
    }
});

test('a batch runs each script on its own when the shared process fails', function () {
    $root = scriptRunnerProject('batch-fails');

    try {
        $runner = scriptRunnerFor($root);

        expect($runner->batch([
            'first'  => 'define("SCRIPT_RUNNER_FIRST", true); echo json_encode(1);',
            'second' => 'exit(1);',
            'third'  => 'echo json_encode(defined("SCRIPT_RUNNER_FIRST"));',
        ]))->toBe(['first' => 1, 'second' => null, 'third' => false]);

        expect($runner->batch([
            'first'  => 'define("SCRIPT_RUNNER_FIRST", true); echo json_encode(1);',
            'second' => 'echo json_encode(defined("SCRIPT_RUNNER_FIRST"));',
        ]))->toBe(['first' => 1, 'second' => false]);
    } finally {
        scriptRunnerCleanup($root);
    }
});

test('a batch with a single script runs it directly', function () {
    $root = scriptRunnerProject('batch-single');

    try {
        expect(scriptRunnerFor($root)->batch(['only' => 'echo json_encode(SCRIPT_RUNNER_BOOTED);']))
            ->toBe(['only' => true])
            ->and(scriptRunnerFor($root)->batch([]))
            ->toBe([]);
    } finally {
        scriptRunnerCleanup($root);
    }
});

test('a batch resolves the scripts inside the PHP environment', function () {
    $host = scriptRunnerProject('batch-host');
    $runtime = scriptRunnerProject('batch-runtime');

    try {
        mkdir($runtime . '/storage/framework', 0777, true);

        $runner = new class($host, ['php'], $runtime) extends ScriptRunner
        {
            public function __construct(string $path, array $command, protected string $runtime)
            {
                parent::__construct($path, $command);
            }

            protected function execute(string $script): ?string
            {
                foreach (glob($this->path . '/storage/framework/lsp-*.php') ?: [] as $file) {
                    copy($file, $this->runtime . '/storage/framework/' . basename($file));
                }

                $process = new Process([PHP_BINARY, $script], $this->runtime);
                $process->mustRun();

                return $process->getOutput();
            }
        };

        expect($runner->batch([
            'first'  => 'echo json_encode(SCRIPT_RUNNER_PROJECT);',
            'second' => 'echo json_encode(SCRIPT_RUNNER_PROJECT);',
        ]))->toBe(['first' => realpath($runtime), 'second' => realpath($runtime)]);
    } finally {
        scriptRunnerCleanup($host, $runtime);
    }
});
