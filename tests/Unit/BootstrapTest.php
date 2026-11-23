<?php

use Symfony\Component\Process\Process;

test('the server boots without crashing or printing deprecations', function (string $command) {
    $process = new Process(
        [PHP_BINARY, '-d', 'display_errors=stdout', '-d', 'log_errors=1', '-d', 'error_log=', 'server', $command],
        dirname(__DIR__, 2),
        ['APP_ENV' => false],
    );

    $process->run();

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->not->toContain('Deprecated')
        ->and($process->getErrorOutput())->not->toContain('Deprecated');
})->with(['--version', 'list']);
