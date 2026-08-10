<?php

declare(strict_types=1);

namespace App\Lsp;

class PintRunner
{
    /**
     * The path to Pint, relative to the project root.
     */
    protected const BINARY = 'vendor/bin/pint';

    /**
     * The maximum number of seconds to wait for Pint to format a document.
     */
    protected const TIMEOUT = 10;

    /**
     * The number of bytes to read from Pint at a time.
     */
    protected const CHUNK = 8192;

    /**
     * The prefix Pint gives the temporary file it buffers stdin into.
     */
    protected const STDIN_PREFIX = 'pint_stdin_';

    /**
     * Whether Pint keeps the document name when reading stdin.
     */
    protected ?bool $keepsDocumentName = null;

    /**
     * Create a new Pint runner instance.
     *
     * @param  array<int, string>  $command
     */
    public function __construct(
        protected string $path,
        protected array $command,
    ) {}

    /**
     * Determine if the project has Pint installed.
     */
    public function available(): bool
    {
        return is_file($this->binary());
    }

    /**
     * Get the absolute path to the project's Pint binary.
     */
    public function binary(): string
    {
        return $this->path . DIRECTORY_SEPARATOR . self::BINARY;
    }

    /**
     * Get the command used to format the document at the given path.
     *
     * Pint reads the document from stdin, so unsaved editor changes are
     * formatted without ever touching the file on disk. The filename is
     * still passed along so Pint can resolve configuration and exclusions.
     *
     * @return array<int, string>
     */
    public function command(string $path): array
    {
        return [
            ...$this->command,
            $this->binary(),
            '--quiet',
            '--no-interaction',
            ...(self::isBlade($path) ? ['--blade'] : []),
            '--stdin-filename',
            $path,
        ];
    }

    /**
     * Determine if Pint keeps the document name when reading stdin.
     *
     * Older versions buffer stdin into a file named after a uniqid, which
     * templates cannot be formatted through at all. There is no way to tell
     * from a template alone, since an unsupported run and an already
     * formatted one both return the document unchanged, so ask Pint to
     * rename a class it can only rename correctly when the name survives.
     */
    public function keepsDocumentName(): bool
    {
        return $this->keepsDocumentName ??= $this->probeDocumentName();
    }

    /**
     * Probe Pint for whether it keeps the document name when reading stdin.
     */
    protected function probeDocumentName(): bool
    {
        $directory = $this->temporaryDirectory();

        if ($directory === null) {
            return false;
        }

        $config = $directory . DIRECTORY_SEPARATOR . 'pint.json';

        try {
            @file_put_contents($config, json_encode([
                'preset' => 'laravel',
                'rules'  => ['psr_autoloading' => true],
            ]));

            $output = $this->run([
                ...$this->command,
                $this->binary(),
                '--quiet',
                '--no-interaction',
                '--config',
                $config,
                '--stdin-filename',
                $directory . DIRECTORY_SEPARATOR . 'Probe.php',
            ], "<?php\n\nnamespace App;\n\nclass Wrong {}\n");

            return $output !== null && str_contains($output, 'class Probe');
        } finally {
            @unlink($config);
            @rmdir($directory);
        }
    }

    /**
     * Get the command used to format the given file in place.
     *
     * Blade templates are only formatted when the rule is enabled, which
     * requires Pint 1.30 or later and the prettier dependencies it installs.
     *
     * @return array<int, string>
     */
    public function fileCommand(string $file): array
    {
        return [
            ...$this->command,
            $this->binary(),
            '--quiet',
            '--no-interaction',
            ...(self::isBlade($file) ? ['--blade'] : []),
            $file,
        ];
    }

    /**
     * Determine if the document at the given path is a Blade template.
     */
    public static function isBlade(string $path): bool
    {
        return str_ends_with($path, '.blade.php');
    }

    /**
     * Format the given contents, returning null when Pint is unable to.
     */
    public function format(string $path, string $contents): ?string
    {
        if (!$this->available()) {
            return null;
        }

        // Templates are only formatted when Pint's Blade fixer sees a path
        // ending in ".blade.php", so they need a real file name wherever
        // Pint would otherwise read them through its own scratch file.
        if (self::isBlade($path) && !$this->keepsDocumentName()) {
            return $this->formatViaFile($path, $contents);
        }

        $output = $this->run($this->command($path), $contents);

        if ($output === null || $output === '') {
            return null;
        }

        // Pint 1.30 and earlier name their temporary stdin file after a uniqid,
        // so filename derived fixers such as psr_autoloading rewrite the
        // document to match it. Reformat through a file that keeps the real
        // name so the class is never renamed to Pint's scratch file.
        if (self::leaksTempFileName($contents, $output)) {
            info('Pint discarded the document name, reformatting through a file.', [
                'path' => $path,
            ]);

            return $this->formatViaFile($path, $contents);
        }

        return $output;
    }

    /**
     * Format the given contents through a temporary file named after the document.
     *
     * Reading stdin is preferred because it is the only mode in which Pint
     * applies the configured exclusions, not because it is faster. The two
     * are within a few milliseconds of each other, since starting Pint
     * costs far more than writing the document out and reading it back.
     */
    protected function formatViaFile(string $path, string $contents): ?string
    {
        $directory = $this->temporaryDirectory();

        if ($directory === null) {
            return null;
        }

        $file = $directory . DIRECTORY_SEPARATOR . basename($path);

        try {
            if (@file_put_contents($file, $contents) === false) {
                return null;
            }

            if ($this->run($this->fileCommand($file), '') === null) {
                return null;
            }

            $formatted = @file_get_contents($file);

            return $formatted === false || $formatted === '' ? null : $formatted;
        } finally {
            @unlink($file);
            @rmdir($directory);
        }
    }

    /**
     * Create a private temporary directory, or null when it cannot be made.
     */
    protected function temporaryDirectory(): ?string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'laravel-lsp-' . bin2hex(random_bytes(8));

        return @mkdir($directory, 0700, true) ? $directory : null;
    }

    /**
     * Run the given command, returning its output, or null when it fails.
     *
     * @param  array<int, string>  $command
     */
    protected function run(array $command, string $input): ?string
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $this->path);

        if (!is_resource($process)) {
            return null;
        }

        [$output, $error, $timedOut] = $this->communicate($pipes, $input);

        if ($timedOut) {
            proc_terminate($process);
        }

        $exitCode = proc_close($process);

        if ($timedOut || $exitCode !== 0) {
            info('Pint runner error.', [
                'command'  => $command,
                'stderr'   => $error,
                'exitCode' => $exitCode,
                'timedOut' => $timedOut,
            ]);

            return null;
        }

        return $output;
    }

    /**
     * Determine if Pint rewrote the document to match its temporary stdin file.
     *
     * Pint buffers stdin into a temporary "pint_stdin_*.php" file, so fixers
     * that derive code from the file name, such as psr_autoloading, rename
     * classes to match it. Formatting must never corrupt the document.
     */
    public static function leaksTempFileName(string $contents, string $output): bool
    {
        return str_contains($output, self::STDIN_PREFIX)
            && !str_contains($contents, self::STDIN_PREFIX);
    }

    /**
     * Write the given contents to Pint while reading its output.
     *
     * Both streams are pumped together so a document larger than the pipe
     * buffer cannot deadlock against a Pint process that has not started
     * reading yet, such as one aborting on missing prettier dependencies.
     *
     * @param  array<int, resource>  $pipes
     * @return array{0: string, 1: string, 2: bool}
     */
    protected function communicate(array $pipes, string $contents): array
    {
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        if ($contents === '') {
            fclose($pipes[0]);
        }

        $open = [1 => $pipes[1], 2 => $pipes[2]];
        $buffers = [1 => '', 2 => ''];
        $deadline = microtime(true) + self::TIMEOUT;

        while ($open !== [] || $contents !== '') {
            $remaining = $deadline - microtime(true);

            if ($remaining <= 0) {
                return [$buffers[1], $buffers[2], true];
            }

            $read = array_values($open);
            $write = $contents === '' ? [] : [$pipes[0]];
            $except = null;

            $selected = stream_select(
                $read,
                $write,
                $except,
                (int) $remaining,
                (int) (fmod($remaining, 1) * 1000000),
            );

            if ($selected === false) {
                return [$buffers[1], $buffers[2], true];
            }

            foreach ($write as $pipe) {
                $written = fwrite($pipe, $contents);

                $contents = $written === false ? '' : substr($contents, $written);

                if ($contents === '') {
                    fclose($pipes[0]);
                }
            }

            foreach ($read as $pipe) {
                $key = array_search($pipe, $open, true);
                $chunk = fread($pipe, self::CHUNK);

                if ($chunk !== false && $chunk !== '') {
                    $buffers[$key] .= $chunk;

                    continue;
                }

                if (feof($pipe)) {
                    fclose($pipe);

                    unset($open[$key]);
                }
            }
        }

        return [$buffers[1], $buffers[2], false];
    }
}
