<?php

use Illuminate\Support\Collection;

function configsFromTemplate(): Collection
{
    ob_start();

    require base_path('app/Lsp/Data/Templates/configs.php');

    return collect(json_decode(ob_get_clean(), true))->keyBy('name');
}

test('configs registered at runtime without a matching config file are kept', function () {
    config([
        'runtime-only.feature.enabled' => true,
        'runtime-only.drivers'         => ['redis', 'database'],
    ]);

    $configs = configsFromTemplate();

    expect($configs->get('runtime-only.feature.enabled'))->toBe([
        'name'  => 'runtime-only.feature.enabled',
        'value' => true,
        'file'  => null,
        'line'  => null,
    ]);

    expect($configs->get('runtime-only.drivers'))->toMatchArray([
        'value' => 'array(...)',
        'file'  => null,
    ]);

    expect($configs)
        ->not->toHaveKey('runtime-only.drivers.0')
        ->not->toHaveKey('runtime-only.drivers.1');

    expect($configs->get('app.name'))
        ->file->toBe('config/app.php');
});
