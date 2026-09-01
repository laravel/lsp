<?php

declare(strict_types=1);

namespace App\Lsp\Support;

use App\Lsp\Project;
use FilesystemIterator;
use Generator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Walks the PHP and Blade files of the workspace.
 *
 * Reference lookups always know the exact string they are looking for, so the
 * walk yields only files whose contents contain it. Parsing is then limited to
 * the handful of files that can possibly match, which keeps a workspace-wide
 * search off the parser for almost every file in the project.
 */
class WorkspaceFiles
{
    /**
     * Directory names that never hold project source.
     */
    protected const SKIPPED_DIRECTORIES = [
        '.git',
        '.idea',
        '.vscode',
        'bootstrap/cache',
        'node_modules',
        'public/build',
        'storage',
        'vendor',
    ];

    /**
     * The largest file worth reading, in bytes.
     */
    protected const MAX_FILE_SIZE = 2097152;

    /**
     * Create a new workspace file walker instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Get workspace files whose contents contain the given needle.
     *
     * @return Generator<string, string>
     */
    public function containing(string $needle): Generator
    {
        if ($needle === '') {
            return;
        }

        foreach ($this->all() as $path) {
            $contents = @file_get_contents($path);

            if ($contents === false || !str_contains($contents, $needle)) {
                continue;
            }

            yield $path => $contents;
        }
    }

    /**
     * Get every PHP file in the workspace.
     *
     * @return Generator<int, string>
     */
    public function all(): Generator
    {
        $basePath = $this->project->path();

        if (!is_dir($basePath)) {
            return;
        }

        $directories = new RecursiveDirectoryIterator(
            $basePath,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO,
        );

        $filtered = new RecursiveCallbackFilterIterator(
            $directories,
            fn (SplFileInfo $file): bool => $this->shouldVisit($file, $basePath),
        );

        foreach (new RecursiveIteratorIterator($filtered) as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile()) {
                yield $file->getPathname();
            }
        }
    }

    /**
     * Determine if the walk should descend into or read the given entry.
     */
    protected function shouldVisit(SplFileInfo $file, string $basePath): bool
    {
        if ($file->isDir()) {
            return !$this->isSkipped($file->getPathname(), $basePath);
        }

        return $file->getExtension() === 'php'
            && $file->getSize() <= self::MAX_FILE_SIZE;
    }

    /**
     * Determine if the directory is excluded from the walk.
     */
    protected function isSkipped(string $path, string $basePath): bool
    {
        $relative = trim(str_replace('\\', '/', substr($path, strlen($basePath))), '/');

        foreach (self::SKIPPED_DIRECTORIES as $skipped) {
            if ($relative === $skipped || str_starts_with($relative, $skipped . '/')) {
                return true;
            }
        }

        return false;
    }
}
