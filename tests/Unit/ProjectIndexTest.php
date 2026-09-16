<?php

use App\Lsp\Project;
use App\Lsp\ProjectIndex;
use App\Lsp\ScriptRunner;
use App\Lsp\Support\FileUri;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;

function projectIndexScripts(array $data = []): ScriptRunner
{
    return new class('/tmp/laravel-lsp-project', ['php'], $data) extends ScriptRunner
    {
        /**
         * @var array<int, array<int, string>>
         */
        public array $batches = [];

        public function __construct(string $path, array $command, protected array $data)
        {
            parent::__construct($path, $command);
        }

        public function batch(array $codes): array
        {
            $this->batches[] = array_keys($codes);

            $results = [];

            foreach (array_keys($codes) as $key) {
                $results[$key] = $this->data[$key] ?? null;
            }

            return $results;
        }

        public function run(string $code): ?string
        {
            throw new RuntimeException('No script should run on its own.');
        }
    };
}

function projectIndex(ScriptRunner $scripts): ProjectIndex
{
    $container = new Container;
    $index = new ProjectIndex($container, $scripts);

    $container->instance(Project::class, new Project(
        FileUri::of('file:///tmp/laravel-lsp-project'),
        [],
        $index,
        $scripts,
    ));

    return $index;
}

test('loading a template provider runs every unloaded template in one batch with snapshots first', function () {
    $scripts = projectIndexScripts();
    $index = projectIndex($scripts);

    $index->routes();

    expect($scripts->batches)->toBe([[
        'configs',
        'appBindings',
        'auth',
        'bladeComponents',
        'customBladeDirectives',
        'debugInfo',
        'inertiaViews',
        'middleware',
        'models',
        'paths',
        'routes',
        'tests',
        'translations',
        'views',
    ]]);

    $index->views();
    $index->configs();
    $index->tests();

    expect($scripts->batches)->toHaveCount(1);
});

test('template output is parsed by its provider', function () {
    $index = projectIndex(projectIndexScripts([
        'routes' => [['name' => 'home']],
        'paths'  => ['base' => '/app'],
        'tests'  => ['Unit' => []],
    ]));

    expect($index->routes())->toBeInstanceOf(Collection::class)
        ->and($index->routes()->all())->toBe([['name' => 'home']])
        ->and($index->paths()->all())->toBe(['base' => '/app'])
        ->and($index->tests())->toBe(['Unit' => []]);
});

test('a template without valid output loads as empty data', function () {
    $index = projectIndex(projectIndexScripts([
        'routes' => [['name' => 'home']],
        'views'  => 'not an array',
        'tests'  => null,
    ]));

    expect($index->views()->all())->toBe([])
        ->and($index->tests())->toBe([])
        ->and($index->routes()->all())->toBe([['name' => 'home']]);
});

test('providers without a template load on their own', function () {
    $scripts = projectIndexScripts();
    $index = projectIndex($scripts);

    expect($index->env()->all())->toBe([])
        ->and($scripts->batches)->toBe([]);
});

test('invalidating reloads only the affected templates', function () {
    $scripts = projectIndexScripts();
    $index = projectIndex($scripts);

    $index->routes();
    $index->invalidate(['routes/web.php']);
    $index->views();

    expect($scripts->batches)->toHaveCount(1);

    $index->routes();

    expect($scripts->batches)->toHaveCount(2)
        ->and($scripts->batches[1])->toBe(['routes']);
});

test('invalidated templates reload together with snapshots first', function () {
    $scripts = projectIndexScripts();
    $index = projectIndex($scripts);

    $index->routes();
    $index->invalidate(['config/app.php']);
    $index->paths();

    expect($scripts->batches)->toHaveCount(2)
        ->and($scripts->batches[1])->toBe(['configs', 'inertiaViews', 'paths']);
});
