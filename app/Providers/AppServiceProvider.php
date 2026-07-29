<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Phar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config([
            'logging.channels.single.path' => $this->getLoggingPath(),
        ]);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Get logging path.
     */
    protected function getLoggingPath(): string
    {
        $dir = Phar::running()
            ? dirname(Phar::running(false)) . '/logs'
            : storage_path('logs');

        if (!$this->isWritableLoggingDirectory($dir)) {
            $dir = sys_get_temp_dir() . '/laravel-lsp/logs';
        }

        File::ensureDirectoryExists($dir);

        return $dir . '/lsp.log';
    }

    /**
     * Determine if logs can be written to the given directory.
     */
    protected function isWritableLoggingDirectory(string $dir): bool
    {
        return is_dir($dir) ? is_writable($dir) : is_writable(dirname($dir));
    }
}
