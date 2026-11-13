<?php

use App\Lsp\Document;
use App\Lsp\Features\Eloquent\EloquentCompletionProvider;
use App\Lsp\Project;
use App\Lsp\ProjectIndex;
use App\Lsp\ScriptRunner;
use App\Lsp\Support\FileUri;

function eloquentCompletions(string $code): array
{
    $index = new class extends ProjectIndex
    {
        public function __construct() {}

        public function models(): array
        {
            return [
                'App\\Models\\User' => [
                    'class'      => 'App\\Models\\User',
                    'attributes' => [
                        ['name' => 'name', 'fillable' => true],
                        ['name' => 'email', 'fillable' => true],
                    ],
                    'relations' => [
                        ['name' => 'addresses', 'type' => 'HasMany', 'related' => 'App\\Models\\Address'],
                    ],
                ],
                'App\\Models\\Address' => [
                    'class'      => 'App\\Models\\Address',
                    'attributes' => [
                        ['name' => 'street', 'fillable' => true],
                        ['name' => 'city', 'fillable' => true],
                    ],
                    'relations' => [],
                ],
            ];
        }
    };

    $project = new Project(
        uri: FileUri::of('file:///project'),
        init: [],
        index: $index,
        scripts: new ScriptRunner('/project', []),
    );

    $code = "<?php\n\nuse App\\Models\\User;\n\n\$user = User::query()->firstOrFail();\n\n" . $code;
    $lines = explode("\n", $code);

    $position = [
        'line'      => count($lines) - 1,
        'character' => strlen(end($lines)),
    ];

    $items = (new EloquentCompletionProvider($project))->get(new Document('file:///project/app/Test.php', $code), $position);

    return array_column($items, 'label');
}

test('completes the model attributes', function () {
    expect(eloquentCompletions("User::create(['"))->toBe(['name', 'email']);
});

test('completes the related model attributes on a relation method call', function () {
    expect(eloquentCompletions("\$user->addresses()->create(['"))->toBe(['street', 'city']);
});

test('completes the related model attributes through builder calls on a relation', function () {
    expect(eloquentCompletions("\$user->addresses()->latest()->create(['"))->toBe(['street', 'city'])
        ->and(eloquentCompletions("\$user->addresses()->where('"))->toBe(['street', 'city']);
});

test('completes the related model attributes on a relation of a fetched model', function () {
    expect(eloquentCompletions("User::find(1)->addresses()->create(['"))->toBe(['street', 'city']);
});
