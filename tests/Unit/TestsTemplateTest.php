<?php

use App\Lsp\ScriptRunner;

test('the tests template constructs the configuration loader with the event emitter PHPUnit 13.4 requires', function () {
    $root = sys_get_temp_dir() . '/lsp-tests-template-' . getmypid();

    $cleanup = function () use ($root) {
        @unlink($root . '/vendor');
        @unlink($root . '/bootstrap/app.php');
        @unlink($root . '/phpunit.xml');
        @unlink($root . '/loader.php');
        @unlink($root . '/loaded.txt');
        @rmdir($root . '/bootstrap');
        @rmdir($root . '/tests');
        @rmdir($root . '/storage/framework');
        @rmdir($root . '/storage');
        @rmdir($root);
    };

    @mkdir($root . '/bootstrap', 0777, true);
    @mkdir($root . '/tests', 0777, true);

    if (!@symlink(base_path('vendor'), $root . '/vendor')) {
        $cleanup();

        $this->markTestSkipped('Unable to create a symlink on this platform.');
    }

    file_put_contents($root . '/bootstrap/app.php', <<<'PHP'
    <?php

    $app = new LaravelZero\Framework\Application(dirname(__DIR__));
    $app->instance('files', new Illuminate\Filesystem\Filesystem);
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);

    return new class
    {
        public function make(string $abstract): object
        {
            return new class
            {
                public function bootstrap(): void {}
            };
        }
    };
    PHP);

    file_put_contents($root . '/phpunit.xml', <<<'XML'
    <phpunit><testsuites><testsuite name="Unit"><directory>tests</directory></testsuite></testsuites></phpunit>
    XML);

    // PHPUnit 13.4 added a required Emitter argument to the XML configuration loader.
    file_put_contents($root . '/loader.php', <<<'PHP'
    <?php

    namespace PHPUnit\TextUI\XmlConfiguration;

    final class Loader
    {
        public function __construct(\PHPUnit\Event\Emitter $emitter) {}

        public function load(string $filename): ?LoadedFromFileConfiguration
        {
            file_put_contents(__DIR__ . '/loaded.txt', $filename);

            return null;
        }
    }
    PHP);

    $runner = new ScriptRunner($root, ['php', '-d', 'auto_prepend_file=' . $root . '/loader.php']);

    try {
        $suites = $runner->json(file_get_contents(base_path('app/Lsp/Data/Templates/tests.php')));
        $loaded = @file_get_contents($root . '/loaded.txt');
    } finally {
        $cleanup();
    }

    expect($loaded)->toBe($root . '/phpunit.xml')
        ->and($suites)->toBe([['name' => 'default', 'files' => []]]);
});
