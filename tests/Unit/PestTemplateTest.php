<?php

use App\Lsp\ScriptRunner;
use Tests\TestCase;

test('the pest template resolves relative test directories', function () {
    // This repo's tests/Pest.php uses a relative ->in('Unit') path.
    $pest = (new ScriptRunner(base_path(), [PHP_BINARY]))->json(
        file_get_contents(base_path('app/Lsp/Data/Templates/pest.php'))
    );

    // This entry is missing when Pest.php is loaded with a plain require
    expect($pest['uses'])->toContain([
        'path'    => 'tests' . DIRECTORY_SEPARATOR . 'Unit',
        'classes' => [TestCase::class],
        'traits'  => [],
    ]);
});
