<?php

use App\Lsp\Detection\DetectedArgument;
use App\Lsp\Features\Assets\AssetDocumentMapper;
use App\Lsp\Project;
use App\Lsp\ProjectIndex;
use App\Lsp\ScriptRunner;
use App\Lsp\Support\FileUri;
use Illuminate\Support\Collection;

function assetArgument(string $value): DetectedArgument
{
    return new DetectedArgument(
        item: [],
        argumentIndex: 0,
        param: [
            'type'  => 'string',
            'value' => $value,
        ],
    );
}

function assetMapper(): AssetDocumentMapper
{
    $index = new class extends ProjectIndex
    {
        public function __construct() {}

        public function assets(): Collection
        {
            return new Collection([
                [
                    'path'     => 'images/preview.png',
                    'fullPath' => '/project/public/images/preview.png',
                ],
            ]);
        }
    };

    $project = new Project(
        uri: FileUri::of('file:///project'),
        init: [],
        index: $index,
        scripts: new ScriptRunner('/project', []),
    );

    return new class($project) extends AssetDocumentMapper
    {
        public function findAsset(DetectedArgument $argument): ?array
        {
            return $this->find($argument);
        }
    };
}

test('finds assets with or without a leading slash', function () {
    $mapper = assetMapper();

    expect($mapper->findAsset(assetArgument('images/preview.png')))->not->toBeNull()
        ->and($mapper->findAsset(assetArgument('/images/preview.png')))->not->toBeNull();
});
