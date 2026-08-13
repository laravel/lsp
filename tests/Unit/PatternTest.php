<?php

use App\Lsp\Support\Pattern;

test('matches paths against watcher patterns', function () {
    expect(Pattern::matches('routes/web.php', '**/[Rr]oute{,s}{.php,/*.php,/**/*.php}'))->toBeTrue();
    expect(Pattern::matches('app/Models/User.php', 'app/{,*,**/*}.php'))->toBeTrue();
    expect(Pattern::matches('resources/views/welcome.blade.php', '**/{resources,Modules/*/resources}/views/**/*.blade.php'))->toBeTrue();
    expect(Pattern::matches('app/Models/User.php', 'lang/{*,**/*}'))->toBeFalse();
});

test('matches windows paths with backslashes', function () {
    expect(Pattern::matches('app\\Models\\User.php', 'app/{,*,**/*}.php'))->toBeTrue();
});

test('compiled patterns are not shared between different patterns', function () {
    expect(Pattern::matches('config/app.php', 'config/{,*,**/*}.php'))->toBeTrue();
    expect(Pattern::matches('config/app.php', 'tests/**/*'))->toBeFalse();
    expect(Pattern::matches('config/app.php', 'config/{,*,**/*}.php'))->toBeTrue();
});

test('matches any pattern and any path', function () {
    expect(Pattern::matchesAny('config/app.php', ['lang/{*,**/*}', 'config/{,*,**/*}.php']))->toBeTrue();
    expect(Pattern::matchesAny('config/app.php', ['lang/{*,**/*}', 'tests/**/*']))->toBeFalse();

    expect(Pattern::matchesAnyPath(['README.md', 'config/app.php'], ['config/{,*,**/*}.php']))->toBeTrue();
    expect(Pattern::matchesAnyPath(['README.md', 'src/main.rs'], ['config/{,*,**/*}.php']))->toBeFalse();
});
