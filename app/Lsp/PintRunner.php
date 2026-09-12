<?php

declare(strict_types=1);

namespace App\Lsp;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

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
        $process = new Process($command, $this->path, null, $input, self::TIMEOUT);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            info('Pint runner timed out.', ['command' => $command]);

            return null;
        }

        if (!$process->isSuccessful()) {
            info('Pint runner error.', [
                'command'  => $command,
                'stderr'   => $process->getErrorOutput(),
                'exitCode' => $process->getExitCode(),
            ]);

            return null;
        }

        return $process->getOutput();
    }
}
