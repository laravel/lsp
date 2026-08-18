<?php

use App\Lsp\Contracts\ExceptionHandler;
use App\Lsp\PhpCommandDetector;
use App\Lsp\Transport\JsonRpcRequest;
use App\Lsp\Transport\JsonRpcResponse;

/**
 * A detector that answers from a canned map of commands instead of the shell.
 */
function detector(string $environment, array $output = []): PhpCommandDetector
{
    $exceptions = new class implements ExceptionHandler
    {
        public function report(Throwable $e): void {}

        public function render(JsonRpcRequest $request, Throwable $e): JsonRpcResponse
        {
            return JsonRpcResponse::error($request->id(), -32603, $e->getMessage());
        }
    };

    return new class('/home/runner/work/project', $environment, $exceptions, $output) extends PhpCommandDetector
    {
        public array $ran = [];

        public function __construct(string $path, string $environment, ExceptionHandler $exceptions, protected array $output)
        {
            parent::__construct($path, $environment, $exceptions);
        }

        protected function run(array $command): ?string
        {
            $this->ran[] = implode(' ', $command);

            return $this->output[implode(' ', $command)] ?? null;
        }
    };
}

test('resolves the Yerd PHP binary', function () {
    expect(detector('yerd', [
        'yerd which php' => "/home/runner/.local/share/yerd/php/php-8.4/bin/php\n",
    ])->detect())->toBe(['/home/runner/.local/share/yerd/php/php-8.4/bin/php']);
});

test('falls back to php when Yerd is not installed', function () {
    expect(detector('yerd')->detect())->toBe(['php']);
});

test('falls back to php when Yerd resolves nothing', function () {
    expect(detector('yerd', ['yerd which php' => "  \n"])->detect())->toBe(['php']);
});

test('resolves the Lerd passthrough command', function () {
    expect(detector('lerd', [
        'lerd php -r echo PHP_BINARY;' => '/usr/local/bin/php',
    ])->detect())->toBe(['lerd', 'php']);
});

test('falls back to php when Lerd is not installed', function () {
    expect(detector('lerd')->detect())->toBe(['php']);
});

test('auto-detection finds Yerd when no other environment answers', function () {
    $detector = detector('auto', [
        'yerd which php' => '/home/runner/.local/share/yerd/php/php-8.4/bin/php',
    ]);

    expect($detector->detect())->toBe(['/home/runner/.local/share/yerd/php/php-8.4/bin/php']);
});

test('auto-detection finds Lerd when no other environment answers', function () {
    $detector = detector('auto', [
        'lerd php -r echo PHP_BINARY;' => '/usr/local/bin/php',
    ]);

    expect($detector->detect())->toBe(['lerd', 'php']);
});

test('auto-detection probes Yerd and Lerd before the local binary', function () {
    $detector = detector('auto');

    $detector->detect();

    $ran = $detector->ran;

    expect(array_search('yerd which php', $ran, true))
        ->toBeLessThan(array_search('php -r echo PHP_BINARY;', $ran, true))
        ->and(array_search('lerd php -r echo PHP_BINARY;', $ran, true))
        ->toBeLessThan(array_search('php -r echo PHP_BINARY;', $ran, true));
});

test('auto-detection still prefers Herd over Yerd and Lerd', function () {
    $detector = detector('auto', [
        'herd which-php'               => '/Users/runner/Library/Application Support/Herd/bin/php84',
        'yerd which php'               => '/home/runner/.local/share/yerd/php/php-8.4/bin/php',
        'lerd php -r echo PHP_BINARY;' => '/usr/local/bin/php',
    ]);

    expect($detector->detect())->toBe(['/Users/runner/Library/Application Support/Herd/bin/php84'])
        ->and($detector->ran)->not->toContain('yerd which php');
});
