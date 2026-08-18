<?php

use App\Lsp\Contracts\Transport;
use App\Lsp\DocumentManager;
use App\Lsp\Server;
use App\Lsp\Support\FileUri;
use App\Lsp\Transport\JsonRpcRequest;
use Illuminate\Container\Container;
use Psr\Log\NullLogger;

/**
 * A transport that records what the server sends and replays what it receives.
 */
function fakeTransport(): Transport
{
    return new class implements Transport
    {
        /** @var array<int, array<string, mixed>> */
        public array $sent = [];

        public ?Closure $handler = null;

        public function onReceive(Closure $handler): void
        {
            $this->handler = $handler;
        }

        public function dispatch(JsonRpcRequest $request, Closure $dispatch): void
        {
            $dispatch($request);
        }

        public function cancel(int|string $id): void {}

        public function run(): void {}

        public function send(string $message): void
        {
            $this->sent[] = json_decode($message, true);
        }

        public function receive(array $message): void
        {
            ($this->handler)(json_encode($message));
        }
    };
}

/**
 * Track the throwaway project roots created by this file.
 */
function serverRoots(): ArrayObject
{
    static $roots = new ArrayObject;

    return $roots;
}

/**
 * Boot a server against a throwaway Laravel project root.
 */
function bootServer(array $positionEncodings): array
{
    $root = sys_get_temp_dir() . '/lsp-' . bin2hex(random_bytes(6));

    mkdir($root, 0777, true);
    touch($root . '/artisan');

    serverRoots()->append($root);

    $transport = fakeTransport();
    $container = new Container;
    $server = new Server($transport, new NullLogger, $container);

    $server->start();

    $transport->receive([
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'initialize',
        'params'  => [
            'rootUri'               => (string) FileUri::fromPath($root),
            'capabilities'          => ['general' => ['positionEncodings' => $positionEncodings]],
            'initializationOptions' => ['phpCommand' => ['php']],
        ],
    ]);

    return [$server, $transport, $container, $root];
}

afterEach(function () {
    foreach (serverRoots() as $root) {
        @unlink($root . '/artisan');
        @rmdir($root);
    }

    serverRoots()->exchangeArray([]);
});

test('advertises the negotiated position encoding', function (array $offered, string $expected) {
    [, $transport] = bootServer($offered);

    expect($transport->sent[0]['result']['capabilities']['positionEncoding'])->toBe($expected);
})->with([
    'prefers utf-8'         => [['utf-16', 'utf-8'], 'utf-8'],
    'accepts utf-16'        => [['utf-16'], 'utf-16'],
    'accepts utf-32'        => [['utf-32'], 'utf-32'],
    'defaults when unknown' => [[], 'utf-16'],
]);

test('converts outgoing diagnostics to utf-16 code units', function () {
    [$server, $transport, $container] = bootServer(['utf-16']);

    $uri = 'file:///project/app/Http/Controllers/HomeController.php';

    $container[DocumentManager::class]->open($uri, "<?php\n\$x = '日本語'; view('x');");

    $server->notify('textDocument/publishDiagnostics', [
        'uri'         => $uri,
        'diagnostics' => [[
            'range' => [
                'start' => ['line' => 1, 'character' => 24],
                'end'   => ['line' => 1, 'character' => 27],
            ],
            'message' => 'View not found.',
        ]],
    ]);

    expect(end($transport->sent)['params']['diagnostics'][0]['range'])->toBe([
        'start' => ['line' => 1, 'character' => 18],
        'end'   => ['line' => 1, 'character' => 21],
    ]);
});

test('leaves outgoing diagnostics alone when utf-8 is negotiated', function () {
    [$server, $transport, $container] = bootServer(['utf-8']);

    $uri = 'file:///project/app/Http/Controllers/HomeController.php';

    $container[DocumentManager::class]->open($uri, "<?php\n\$x = '日本語'; view('x');");

    $server->notify('textDocument/publishDiagnostics', [
        'uri'         => $uri,
        'diagnostics' => [[
            'range' => [
                'start' => ['line' => 1, 'character' => 24],
                'end'   => ['line' => 1, 'character' => 27],
            ],
        ]],
    ]);

    expect(end($transport->sent)['params']['diagnostics'][0]['range']['start'])
        ->toBe(['line' => 1, 'character' => 24]);
});
