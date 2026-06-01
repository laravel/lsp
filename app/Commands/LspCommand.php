<?php

namespace App\Commands;

use App\Lsp\Server;
use LaravelZero\Framework\Commands\Command;

class LspCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lsp';

    /**
     * The console command description.
     */
    protected $description = 'Start the LSP server';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Server::stdio()->start();
    }
}
