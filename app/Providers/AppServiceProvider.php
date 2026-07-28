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
        if (!Phar::running()) {
            return storage_path('logs/lsp.log');
        }

        File::ensureDirectoryExists(
            $dir = $this->getCompiledLoggingDirectory()
        );

        return $dir . '/lsp.log';
    }

    /**
     * Get the logging directory when running as a compiled binary.
     */
    protected function getCompiledLoggingDirectory(): string
    {
        $dir = dirname(Phar::running(false)) . '/logs';
        $dirIsWritable = is_dir($dir) ? is_writable($dir) : is_writable(dirname($dir));

        if ($dirIsWritable) {
            return $dir;
        }

        return sys_get_temp_dir() . '/laravel-lsp/logs';
    }
}
