<?php

use App\Lsp\Detection\DetectedArgument;
use App\Parser\DetectWalker;

function firstStringParam(string $code): ?array
{
    $context = (new DetectWalker("<?php\n" . $code))->walk();

    $find = function (array $node) use (&$find): ?array {
        if (($node['type'] ?? null) === 'string') {
            return $node;
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $found = is_string(key($value)) ? $find($value) : null;

                if ($found === null) {
                    foreach ($value as $item) {
                        if (is_array($item) && ($found = $find($item)) !== null) {
                            break;
                        }
                    }
                }

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    };

    return $find(json_decode($context->toJson(), true));
}

test('plain strings are not marked as interpolated', function (string $code) {
    $param = firstStringParam($code);

    expect($param)->not->toBeNull();
    expect($param['interpolated'] ?? false)->toBeFalse();
})->with([
    "config('auth.passwords.users.table');",
    'config("auth.passwords.users.table");',
    "config('auth.passwords.\$broker.table');",
    'config("auth.passwords.\\$broker.table");',
    "config(<<<'EOT'\nauth.passwords.users.table\nEOT);",
]);

test('interpolated strings are marked as interpolated', function (string $code) {
    $param = firstStringParam($code);

    expect($param)->not->toBeNull();
    expect($param['interpolated'] ?? false)->toBeTrue();
})->with([
    'config("auth.passwords.$broker.table");',
    'config("auth.passwords.{$broker}.table");',
    'config("auth.passwords.{$this->broker()}.table");',
    'config("auth.passwords.{$model->broker()}.table");',
    'config("auth.passwords.$config[broker].table");',
    'config("auth.passwords.$model->broker.table");',
    "config(<<<EOT\nauth.passwords.\$broker.table\nEOT);",
]);

test('a detected argument reports whether its string is interpolated', function () {
    $plain = new DetectedArgument([], 0, ['type' => 'string', 'value' => 'a.b']);
    $interpolated = new DetectedArgument([], 0, ['type' => 'string', 'value' => 'a.{$b}', 'interpolated' => true]);

    expect($plain->isInterpolated())->toBeFalse();
    expect($interpolated->isInterpolated())->toBeTrue();
});
