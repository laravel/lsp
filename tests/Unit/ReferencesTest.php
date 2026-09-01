<?php

use App\Lsp\DocumentManager;
use App\Lsp\FeatureRegistry;
use App\Lsp\Methods\TextDocumentReferences;
use App\Lsp\Project;
use App\Lsp\ProjectIndex;
use App\Lsp\ScriptRunner;
use App\Lsp\Support\FileUri;
use App\Lsp\Support\WorkspaceFiles;
use App\Lsp\Transport\JsonRpcRequest;
use Illuminate\Container\Container;

/**
 * Build a throwaway project on disk from a map of relative paths to contents.
 */
function workspace(array $files): string
{
    $root = sys_get_temp_dir() . '/lsp-references-' . bin2hex(random_bytes(6));

    foreach ($files as $path => $contents) {
        $target = $root . '/' . $path;

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, $contents);
    }

    return $root;
}

function removeWorkspace(string $root): void
{
    $paths = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($paths as $path) {
        $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
    }

    rmdir($root);
}

function projectAt(string $root, array $options = []): Project
{
    $uri = FileUri::fromPath($root);

    return new Project($uri, $options, new ProjectIndex(new Container), new ScriptRunner($root, ['php']));
}

/**
 * Run a textDocument/references request against the given workspace.
 */
function references(string $root, string $uri, array $position, array $options = []): array
{
    $container = new Container;
    $project = projectAt($root, $options);

    $container->instance(Project::class, $project);

    $documents = new DocumentManager;
    $documents->open($uri, (string) file_get_contents(FileUri::of($uri)->path()));

    $method = new TextDocumentReferences($documents, new FeatureRegistry($container), $project);

    $response = $method->handle(new JsonRpcRequest(1, 'textDocument/references', [
        'textDocument' => ['uri' => $uri],
        'position'     => $position,
        'context'      => ['includeDeclaration' => true],
    ]));

    return $response->toArray()['result'];
}

test('finds every workspace reference to a route name', function () {
    $root = workspace([
        'artisan'                            => '',
        'routes/web.php'                     => "<?php\nRoute::get('/users', [UserController::class, 'index'])->name('users.index');\n",
        'app/Http/Controllers/Home.php'      => "<?php\nfunction go() { return redirect()->route('users.index'); }\n",
        'resources/views/nav.blade.php'      => "<a href=\"{{ route('users.index') }}\">Users</a>\n",
        'app/Http/Controllers/Other.php'     => "<?php\nfunction go() { return route('users.create'); }\n",
        'vendor/acme/pkg/src/Ignored.php'    => "<?php\nroute('users.index');\n",
    ]);

    $uri = (string) FileUri::fromPath($root . '/app/Http/Controllers/Home.php');

    $found = references($root, $uri, ['line' => 1, 'character' => 42]);

    $uris = array_map(fn (array $location): string => $location['uri'], $found);

    // The ->name() call in routes/web.php declares the route rather than
    // referencing it, so it is not a reference.
    expect($found)->toHaveCount(2)
        ->and($uris)->toContain($uri)
        ->and($uris)->toContain((string) FileUri::fromPath($root . '/resources/views/nav.blade.php'))
        ->and($uris)->not->toContain((string) FileUri::fromPath($root . '/vendor/acme/pkg/src/Ignored.php'));

    removeWorkspace($root);
});

test('returns nothing when the cursor is not on a known symbol', function () {
    $root = workspace([
        'artisan'                       => '',
        'app/Http/Controllers/Home.php' => "<?php\nfunction go() { return route('users.index'); }\n",
    ]);

    $uri = (string) FileUri::fromPath($root . '/app/Http/Controllers/Home.php');

    expect(references($root, $uri, ['line' => 1, 'character' => 2]))->toBe([]);

    removeWorkspace($root);
});

test('honours the referencesProvider option', function () {
    $root = workspace([
        'artisan'                       => '',
        'app/Http/Controllers/Home.php' => "<?php\nfunction go() { return route('users.index'); }\n",
        'routes/web.php'                => "<?php\nRoute::get('/u', 'x')->name('users.index');\n",
    ]);

    $uri = (string) FileUri::fromPath($root . '/app/Http/Controllers/Home.php');

    expect(references($root, $uri, ['line' => 1, 'character' => 35]))->not->toBeEmpty()
        ->and(references($root, $uri, ['line' => 1, 'character' => 35], ['referencesProvider' => false]))
        ->toBe([]);

    removeWorkspace($root);
});

test('finds view references across php and blade files', function () {
    $root = workspace([
        'artisan'                          => '',
        'app/Http/Controllers/Home.php'    => "<?php\nfunction show() { return view('pages.home'); }\n",
        'resources/views/layout.blade.php' => "@include('pages.home')\n",
        'resources/views/other.blade.php'  => "@include('pages.about')\n",
    ]);

    $uri = (string) FileUri::fromPath($root . '/app/Http/Controllers/Home.php');

    $found = references($root, $uri, ['line' => 1, 'character' => 36]);

    expect($found)->toHaveCount(2);

    removeWorkspace($root);
});

test('the workspace walker skips vendor and non-php files', function () {
    $root = workspace([
        'artisan'                         => '',
        'app/Example.php'                 => '<?php // needle',
        'resources/views/a.blade.php'     => '{{ "needle" }}',
        'resources/js/app.js'             => '// needle',
        'vendor/acme/src/Vendor.php'      => '<?php // needle',
        'node_modules/pkg/index.php'      => '<?php // needle',
        'storage/framework/views/abc.php' => '<?php // needle',
    ]);

    $paths = [];

    foreach ((new WorkspaceFiles(projectAt($root)))->containing('needle') as $path => $contents) {
        $paths[] = str_replace($root . '/', '', $path);
    }

    sort($paths);

    expect($paths)->toBe(['app/Example.php', 'resources/views/a.blade.php']);

    removeWorkspace($root);
});

test('the workspace walker reads the editor copy of an open document', function () {
    $root = workspace([
        'artisan'                       => '',
        'app/Http/Controllers/Home.php' => "<?php\nfunction go() { return route('users.index'); }\n",
        'routes/web.php'                => "<?php\nRoute::get('/u', 'x')->name('users.index');\n",
    ]);

    $uri = (string) FileUri::fromPath($root . '/app/Http/Controllers/Home.php');
    $container = new Container;
    $project = projectAt($root);

    $container->instance(Project::class, $project);

    $documents = new DocumentManager;

    // The editor holds a second, unsaved reference the file on disk lacks.
    $documents->open($uri, "<?php\nfunction go() { return route('users.index'); }\nfunction b() { return route('users.index'); }\n");

    $method = new TextDocumentReferences($documents, new FeatureRegistry($container), $project);

    $found = $method->handle(new JsonRpcRequest(1, 'textDocument/references', [
        'textDocument' => ['uri' => $uri],
        'position'     => ['line' => 1, 'character' => 35],
    ]))->toArray()['result'];

    $inDocument = array_filter($found, fn (array $location): bool => $location['uri'] === $uri);

    expect($inDocument)->toHaveCount(2);

    removeWorkspace($root);
});
