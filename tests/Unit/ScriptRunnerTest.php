<?php

use App\Lsp\ScriptRunner;

function scriptRunnerProject(): string
{
    $path = sys_get_temp_dir() . '/laravel-lsp-script-runner-' . bin2hex(random_bytes(4));

    mkdir($path . '/storage/framework', 0777, true);

    return $path;
}

function scriptRunnerFakePhp(string $body): string
{
    $file = sys_get_temp_dir() . '/laravel-lsp-fake-php-' . bin2hex(random_bytes(4)) . '.php';

    file_put_contents($file, "<?php\n" . $body . "\n");

    return $file;
}

/**
 * @return array{0: string, 1: string, 2: ScriptRunner}
 */
function scriptRunner(string $body): array
{
    $project = scriptRunnerProject();
    $fakePhp = scriptRunnerFakePhp($body);

    return [
        $project,
        $fakePhp,
        new ScriptRunner($project, [PHP_BINARY, $fakePhp]),
    ];
}

function scriptRunnerCleanup(string ...$paths): void
{
    foreach ($paths as $path) {
        if ($path === '' || !file_exists($path)) {
            continue;
        }

        if (is_file($path)) {
            @unlink($path);

            continue;
        }

        $script = $path . '/storage/framework';

        if (is_dir($script)) {
            foreach (glob($script . '/lsp-*.php') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($script);
            @rmdir($path . '/storage');
            @rmdir($path);
        }
    }
}

test('json decodes successful tinker output', function () {
    [$project, $fakePhp, $runner] = scriptRunner("echo json_encode(['ok' => true]);");

    try {
        expect($runner->json('unused'))->toBe(['ok' => true]);
    } finally {
        scriptRunnerCleanup($project, $fakePhp);
    }
});

test('json returns null when the process output is not json', function () {
    [$project, $fakePhp, $runner] = scriptRunner("echo 'not-json';");

    try {
        expect($runner->json('unused'))->toBeNull();
    } finally {
        scriptRunnerCleanup($project, $fakePhp);
    }
});

test('json returns null when the process fails', function () {
    [$project, $fakePhp, $runner] = scriptRunner("fwrite(STDERR, str_repeat('e', 4000)); exit(1);");

    try {
        expect($runner->json('unused'))->toBeNull();
    } finally {
        scriptRunnerCleanup($project, $fakePhp);
    }
});

test('command returns the configured php command', function () {
    $project = scriptRunnerProject();

    try {
        $runner = new ScriptRunner($project, ['./vendor/bin/sail', 'php']);

        expect($runner->command())->toBe(['./vendor/bin/sail', 'php']);
    } finally {
        scriptRunnerCleanup($project);
    }
});
