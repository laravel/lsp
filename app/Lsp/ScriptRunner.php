<?php

declare(strict_types=1);

namespace App\Lsp;

use Symfony\Component\Process\Process;
use Throwable;

class ScriptRunner
{
    /**
     * PHP error types to suppress while running project scripts.
     */
    protected const SUPPRESSED_ERROR_TYPES = 'E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING | E_DEPRECATED | E_USER_DEPRECATED';

    /**
     * Whether the project can be bootstrapped without artisan.
     */
    protected ?bool $bootable = null;

    /**
     * Whether scripts may share a process.
     *
     * Turned off once a shared process fails, so a project that cannot run
     * its scripts together does not pay for the attempt on every load.
     */
    protected bool $batchable = true;

    /**
     * Create a new PHP runner instance.
     *
     * @param  array<int, string>  $command
     */
    public function __construct(
        protected string $path,
        protected array $command,
    ) {}

    /**
     * Get the PHP command used to run scripts.
     *
     * @return array<int, string>
     */
    public function command(): array
    {
        return $this->command;
    }

    /**
     * Run PHP code in the user's Laravel application.
     */
    public function run(string $code): ?string
    {
        $script = $this->write($code);

        if ($script === null) {
            info('PHP runner error.', [
                'message' => 'Unable to write the project script.',
                'path'    => $this->path,
            ]);

            return null;
        }

        try {
            return $this->execute($script);
        } finally {
            @unlink($this->path . '/' . $script);
        }
    }

    /**
     * Run several PHP scripts in the user's Laravel application and decode each output as JSON.
     *
     * Booting the application dominates the cost of a script, so the scripts share a
     * single process. Each one runs in its own scope with its output captured on its
     * own, and a script that throws yields null without affecting the others. When the
     * shared process fails, every script is run on its own instead, now and for the
     * rest of the session.
     *
     * @param  array<string, string>  $codes
     * @return array<string, mixed>
     */
    public function batch(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        if (count($codes) === 1) {
            return array_map($this->json(...), $codes);
        }

        $outputs = $this->batchable ? $this->runBatch($codes) : null;

        if ($outputs === null) {
            $this->batchable = false;

            $outputs = array_map($this->run(...), $codes);
        }

        return array_map($this->decode(...), $outputs);
    }

    /**
     * Run the given scripts in a single process and capture each output.
     *
     * @param  array<string, string>  $codes
     * @return array<string, string|null>|null
     */
    protected function runBatch(array $codes): ?array
    {
        $id = bin2hex(random_bytes(8));
        $scripts = [];
        $written = [];

        foreach (array_keys($codes) as $index => $key) {
            $scripts[$key] = 'lsp-' . $id . '-' . $index . '.php';
        }

        try {
            foreach ($codes as $key => $code) {
                $file = $this->store($scripts[$key], '<?php' . PHP_EOL . $this->normalize($code));

                if ($file === null) {
                    return null;
                }

                $written[] = $file;
            }

            $script = $this->write($this->batchCode($scripts));

            if ($script === null) {
                return null;
            }

            $written[] = $script;

            return $this->outputs($this->execute($script), $codes);
        } finally {
            foreach ($written as $file) {
                @unlink($this->path . '/' . $file);
            }
        }
    }

    /**
     * Get the code that runs the given scripts one after another and reports every output.
     *
     * Each script is included inside its own closure so its variables stay local, the
     * error level is restored before each one, and the script's own output is buffered.
     *
     * @param  array<string, string>  $scripts
     */
    protected function batchCode(array $scripts): string
    {
        return implode(PHP_EOL, [
            '$__level = error_reporting();',
            '$__outputs = [];',
            '$__errors = [];',
            'foreach (' . var_export($scripts, true) . ' as $__key => $__script) {',
            '    error_reporting($__level);',
            '    ob_start();',
            '    try {',
            "        (static function () use (\$__script) { include __DIR__ . '/' . \$__script; })();",
            '        $__outputs[$__key] = ob_get_clean();',
            '    } catch (Throwable $__e) {',
            '        ob_end_clean();',
            '        $__outputs[$__key] = null;',
            "        \$__errors[\$__key] = get_class(\$__e) . ': ' . \$__e->getMessage() . ' in ' . \$__e->getFile() . ':' . \$__e->getLine();",
            '    }',
            '}',
            "echo json_encode(['outputs' => \$__outputs, 'errors' => \$__errors]);",
        ]);
    }

    /**
     * Extract the output of every script from the output of the shared process.
     *
     * @param  array<string, string>  $codes
     * @return array<string, string|null>|null
     */
    protected function outputs(?string $output, array $codes): ?array
    {
        $decoded = $output === null ? null : json_decode($output, true);

        if (!is_array($decoded) || !is_array($decoded['outputs'] ?? null)) {
            return null;
        }

        foreach ($decoded['errors'] ?? [] as $key => $error) {
            info('PHP runner error.', [
                'script' => $key,
                'error'  => $error,
            ]);
        }

        $outputs = [];

        foreach (array_keys($codes) as $key) {
            if (!array_key_exists($key, $decoded['outputs'])) {
                return null;
            }

            $outputs[$key] = is_string($decoded['outputs'][$key]) ? $decoded['outputs'][$key] : null;
        }

        return $outputs;
    }

    /**
     * Execute the given script and get its output.
     */
    protected function execute(string $script): ?string
    {
        try {
            $process = new Process($this->arguments($script), $this->path, timeout: null);

            $process->run();

            if (!$process->isSuccessful()) {
                info('PHP runner error.', [
                    'command'  => $process->getCommandLine(),
                    'stdout'   => $process->getOutput(),
                    'stderr'   => $process->getErrorOutput(),
                    'exitCode' => $process->getExitCode(),
                ]);

                return null;
            }

            return $process->getOutput();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Get the process arguments used to run the given script.
     *
     * @return array<int, string>
     */
    protected function arguments(string $script): array
    {
        $arguments = [
            ...$this->command,
            '-d',
            'error_reporting=E_ALL & ~(' . self::SUPPRESSED_ERROR_TYPES . ')',
        ];

        return $this->bootable()
            ? [...$arguments, $script]
            : [...$arguments, 'artisan', 'tinker', '--execute', 'require ' . var_export($script, true) . ';'];
    }

    /**
     * Determine if the project can be bootstrapped without artisan.
     */
    protected function bootable(): bool
    {
        return $this->bootable ??= is_file($this->path . '/vendor/autoload.php')
            && is_file($this->path . '/bootstrap/app.php');
    }

    /**
     * Get the lines that bootstrap the application inside the script.
     *
     * @return array<int, string>
     */
    protected function bootstrap(): array
    {
        if (!$this->bootable()) {
            return [];
        }

        return [
            "define('LARAVEL_START', microtime(true));",
            "require getcwd() . '/vendor/autoload.php';",
            "\$app = require getcwd() . '/bootstrap/app.php';",
            '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();',
        ];
    }

    /**
     * Write the script to a file inside the project.
     */
    protected function write(string $code): ?string
    {
        return $this->store('lsp-' . bin2hex(random_bytes(8)) . '.php', $this->code($code));
    }

    /**
     * Store a file inside the project's framework storage.
     */
    protected function store(string $name, string $contents): ?string
    {
        $script = 'storage/framework/' . $name;
        $path = $this->path . '/' . $script;
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
            return null;
        }

        return @file_put_contents($path, $contents) === false ? null : $script;
    }

    /**
     * Get PHP code with LSP template helpers available.
     */
    protected function code(string $code): string
    {
        return implode(PHP_EOL, [
            '<?php',
            ...$this->bootstrap(),
            'error_reporting(error_reporting() & ~(' . self::SUPPRESSED_ERROR_TYPES . '));',
            $this->normalize(file_get_contents(__DIR__ . '/Data/Templates/global.php') ?: ''),
            $this->normalize($code),
        ]);
    }

    /**
     * Normalize PHP code before passing it to tinker.
     */
    protected function normalize(string $code): string
    {
        return str_starts_with($code, '<?php')
            ? ltrim(substr($code, 5))
            : $code;
    }

    /**
     * Run PHP code and decode the output as JSON.
     */
    public function json(string $code): mixed
    {
        return $this->decode($this->run($code));
    }

    /**
     * Decode the output of a script as JSON.
     */
    protected function decode(?string $output): mixed
    {
        if ($output === null) {
            return null;
        }

        $decoded = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }
}
