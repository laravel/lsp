<?php

declare(strict_types=1);

namespace App\Lsp;

class PintRunner
{
    /**
     * The default path to Pint, relative to the project root.
     */
    public const BINARY = 'vendor/bin/pint';

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
     * Create a new Pint runner instance.
     *
     * @param  array<int, string>  $command
     */
    public function __construct(
        protected string $path,
        protected array $command,
        protected string $pint = self::BINARY,
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
        $pint = trim($this->pint);
        $pint = $pint === '' ? self::BINARY : self::expandHome($pint);

        return self::isAbsolute($pint) ? $pint : $this->path . DIRECTORY_SEPARATOR . $pint;
    }

    /**
     * Expand a leading "~" in the given path.
     *
     * Pint is run without a shell, so a configured path pointing at a home
     * directory would never be expanded for us.
     */
    protected static function expandHome(string $path): string
    {
        if ($path !== '~' && !str_starts_with($path, '~/') && !str_starts_with($path, '~\\')) {
            return $path;
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE');

        return $home === false || $home === '' ? $path : $home . substr($path, 1);
    }

    /**
     * Determine if the given path is absolute on either platform.
     *
     * A configured path may point outside the project, such as a Pint that
     * was installed globally, so it is used as given rather than resolved
     * against the project root.
     */
    protected static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    /**
     * Get the command used to format the document at the given path.
     *
     * Pint reads the document from stdin, so unsaved editor changes are
     * formatted without ever touching the file on disk. The name is passed
     * along so Pint can resolve configuration, exclusions, and the fixers
     * that derive their behavior from it.
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

        $path = $this->physicalPath($path);

        $output = $this->run($this->command($path), $contents);

        if ($output === null || $output === '') {
            return null;
        }

        // Pint 1.30.4 and earlier named their stdin file after a uniqid, so
        // psr_autoloading renames the document's class to match it. Decline
        // to format rather than hand the editor a corrupted buffer.
        if (self::leaksTempFileName($contents, $output)) {
            info('Pint discarded the document name, skipping formatting.', [
                'path' => $path,
            ]);

            return null;
        }

        return $output;
    }

    /**
     * Rewrite the document path to sit under the project's physical root.
     *
     * Pint resolves its exclusions against the working directory, which the
     * operating system reports with symlinks already resolved. A root reached
     * through a symlink would never match, quietly formatting files the
     * project excludes, so hand Pint a path in the same terms.
     */
    protected function physicalPath(string $path): string
    {
        $root = realpath($this->path);

        if ($root === false || $root === $this->path || !str_starts_with($path, $this->path)) {
            return $path;
        }

        return $root . substr($path, strlen($this->path));
    }

    /**
     * Determine if Pint rewrote the document to match its temporary stdin file.
     */
    public static function leaksTempFileName(string $contents, string $output): bool
    {
        return str_contains($output, self::STDIN_PREFIX)
            && !str_contains($contents, self::STDIN_PREFIX);
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
