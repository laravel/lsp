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
     * Run PHP code in the user's Laravel application via artisan tinker.
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
            $process = new Process([
                ...$this->command,
                '-d',
                'error_reporting=E_ALL & ~(' . self::SUPPRESSED_ERROR_TYPES . ')',
                'artisan',
                'tinker',
                '--execute',
                'require ' . var_export($script, true) . ';',
            ], $this->path, timeout: null);

            $process->run();

            if (!$process->isSuccessful()) {
                info('PHP runner error.', [
                    'command'  => $process->getCommandLine(),
                    'stdout'   => $this->truncateOutput($process->getOutput()),
                    'stderr'   => $this->truncateOutput($process->getErrorOutput()),
                    'exitCode' => $process->getExitCode(),
                ]);

                return null;
            }

            return $process->getOutput();
        } catch (Throwable $e) {
            report($e);

            return null;
        } finally {
            @unlink($this->path . '/' . $script);
        }
    }

    /**
     * Write the script to a file inside the project.
     */
    protected function write(string $code): ?string
    {
        $script = 'storage/framework/lsp-' . bin2hex(random_bytes(8)) . '.php';
        $path = $this->path . '/' . $script;
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0777, true)) {
            return null;
        }

        return @file_put_contents($path, $this->code($code)) === false ? null : $script;
    }

    /**
     * Get PHP code with LSP template helpers available.
     */
    protected function code(string $code): string
    {
        return implode(PHP_EOL, [
            '<?php',
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
        $output = $this->run($code);

        if ($output === null) {
            return null;
        }

        $decoded = json_decode($output, true);
        unset($output);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * Truncate process output before writing it to the log.
     */
    protected function truncateOutput(string $output, int $limit = 2000): string
    {
        $bytes = strlen($output);

        if ($bytes <= $limit) {
            return $output;
        }

        return substr($output, 0, $limit) . '... (truncated, ' . $bytes . ' bytes)';
    }
}
