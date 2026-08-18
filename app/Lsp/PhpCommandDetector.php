<?php

declare(strict_types=1);

namespace App\Lsp;

use App\Lsp\Contracts\ExceptionHandler;
use Symfony\Component\Process\Process;
use Throwable;

class PhpCommandDetector
{
    /**
     * Create a new PHP environment detector instance.
     */
    public function __construct(
        protected string $path,
        protected string $environment,
        protected ExceptionHandler $exceptions,
    ) {}

    /**
     * Resolve the PHP command for the configured environment.
     *
     * @return string[]
     */
    public function detect(): array
    {
        return match ($this->environment) {
            'herd'  => $this->herd(),
            'valet' => $this->valet(),
            'sail'  => $this->sail(),
            'lando' => $this->lando(),
            'ddev'  => $this->ddev(),
            'yerd'  => $this->yerd(),
            'lerd'  => $this->lerd(),
            'local' => $this->local(),
            'auto'  => $this->auto(),
            default => ['php'],
        };
    }

    /**
     * Auto-detect the PHP command.
     *
     * @return string[]
     */
    protected function auto(): array
    {
        foreach (['herd', 'valet', 'sail', 'lando', 'ddev', 'local'] as $environment) {
            $command = $this->{$environment}();

            if ($command !== ['php']) {
                return $command;
            }
        }

        return ['php'];
    }

    /**
     * Resolve the Herd PHP command.
     *
     * @return string[]
     */
    protected function herd(): array
    {
        $output = $this->run(['herd', 'which-php']);

        if ($output === null || str_contains($output, 'No usable PHP version found')) {
            return ['php'];
        }

        return $this->binaryCommand($output);
    }

    /**
     * Resolve the Valet PHP command.
     *
     * @return string[]
     */
    protected function valet(): array
    {
        return $this->binaryCommand($this->run(['valet', 'which-php']));
    }

    /**
     * Resolve the Sail PHP command.
     *
     * @return string[]
     */
    protected function sail(): array
    {
        return $this->run(['./vendor/bin/sail', 'ps']) === null
            ? ['php']
            : ['./vendor/bin/sail', 'php'];
    }

    /**
     * Resolve the Lando PHP command.
     *
     * @return string[]
     */
    protected function lando(): array
    {
        return $this->run(['lando', 'php', '-r', 'echo PHP_BINARY;']) === null
            ? ['php']
            : ['lando', 'php'];
    }

    /**
     * Resolve the DDEV PHP command.
     *
     * @return string[]
     */
    protected function ddev(): array
    {
        return $this->run(['ddev', 'php', '-r', 'echo PHP_BINARY;']) === null
            ? ['php']
            : ['ddev', 'php'];
    }

    /**
     * Resolve the Yerd PHP command.
     *
     * Yerd builds native PHP binaries on the host and resolves the version from
     * the site the working directory belongs to, so "yerd which php" reports the
     * same binary "yerd exec php" would run.
     *
     * @return string[]
     */
    protected function yerd(): array
    {
        return $this->binaryCommand($this->run(['yerd', 'which', 'php']));
    }

    /**
     * Resolve the Lerd PHP command.
     *
     * Lerd runs PHP inside a rootless Podman container, so the binary path it
     * reports is meaningless on the host. Its "lerd php" passthrough execs into
     * the container the working directory's site is served from.
     *
     * @return string[]
     */
    protected function lerd(): array
    {
        return $this->run(['lerd', 'php', '-r', 'echo PHP_BINARY;']) === null
            ? ['php']
            : ['lerd', 'php'];
    }

    /**
     * Resolve the local PHP command.
     *
     * @return string[]
     */
    protected function local(): array
    {
        return $this->binaryCommand($this->run(['php', '-r', 'echo PHP_BINARY;']));
    }

    /**
     * Convert a detected PHP binary path into a command.
     *
     * @return string[]
     */
    protected function binaryCommand(?string $output): array
    {
        $binary = trim((string) $output);

        return $binary === '' ? ['php'] : [$binary];
    }

    /**
     * Run a command in the workspace path.
     *
     * @param  string[]  $command
     */
    protected function run(array $command): ?string
    {
        try {
            $process = new Process($command, $this->path, timeout: null);

            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (Throwable $e) {
            $this->exceptions->report($e);

            return null;
        }
    }
}
