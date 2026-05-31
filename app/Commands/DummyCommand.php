<?php

namespace App\Commands;

use App\Lsp\Server;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class DummyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dummy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected int $id = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Server::stdio()->start();
    }

    /**
     * Send dummy initialization request.
     */
    protected function sendInitializationRequest(): void
    {
        $this->send([
            'processId' => null,
            'clientInfo' => [
                'name' => 'dummy-client',
                'version' => '0.0.1',
            ],
            'rootUri' => 'file:///tmp/dummy-project',
            'capabilities' => [],
        ]);
    }

    /**
     * Simulate sending the message.
     */
    protected function send(array $params): void
    {
        $message = json_encode([
            'jsonrpc' => '2.0',
            'id' => $this->id++,
            'params' => $params,
        ]);

        $length = strlen($message);

        fwrite(STDOUT, "Content-Length: {$length}\r\n\r\n{$message}");
    }
}
