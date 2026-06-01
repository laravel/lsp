<?php

declare(strict_types=1);

namespace App\Lsp;

class ScriptRunner
{
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
        $command = [
            ...$this->command,
            'artisan',
            'tinker',
            '--execute',
            $this->code($code),
        ];

        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $this->path);

        if (!is_resource($process)) {
            return null;
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            info('PHP runner error.', [
                'command'  => $command,
                'stdout'   => $output,
                'stderr'   => $error,
                'exitCode' => $exitCode,
            ]);

            return null;
        }

        return $output !== false ? $output : null;
    }

    /**
     * Get PHP code with LSP template helpers available.
     */
    protected function code(string $code): string
    {
        return implode(PHP_EOL, [
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

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }
}
