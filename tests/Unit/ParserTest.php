<?php

use App\Parser\Walker;

function fromFile($file)
{
    return file_get_contents(__DIR__ . '/../snippets/' . $file . '.php');
}

function createContext($values)
{
    return json_encode(['type' => 'base', 'children' => $values], JSON_PRETTY_PRINT);
}

function contextResult($file, $dump = false)
{
    $code = fromFile($file);
    $walker = new Walker($code, true);

    $context = $walker->walk();

    if ($dump === true) {
        dd($context);
    } elseif ($dump === 'json') {
        dd($context->toJson(JSON_PRETTY_PRINT));
    } elseif ($dump === 'array') {
        dd($context->toArray());
    }

    return $context->toJson(JSON_PRETTY_PRINT);
}

test('basic function', function () {
    expect(contextResult('basic-function'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'render',
            'className'      => null,
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 0,
                'children'            => [],
            ],
            'children' => [],
        ],
    ]));
});

test('should not parse because of quote is not open', function () {
    // TODO: A single " is somehow translated string literal and doesn't work correctly
    expect(contextResult('no-parse-closed-string'))->toBe(createContext([]));
});

test('basic function with params', function () {
    expect(contextResult('basic-function-with-param'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'render',
            'className'      => null,
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 1,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'  => 'string',
                                'value' => 'my-view',
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('basic static method', function () {
    expect(contextResult('basic-static-method'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'where',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 0,
                'children'            => [],
            ],
            'children' => [],
        ],
    ]));
});

test('basic static method with params', function () {
    expect(contextResult('basic-static-method-with-params'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'where',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 1,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'  => 'string',
                                'value' => 'email',
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('chained static method with params', function () {
    expect(contextResult('chained-static-method-with-params'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'orWhere',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 1,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'  => 'string',
                                'value' => 'name',
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [
                [
                    'type'       => 'methodCall',
                    'methodName' => 'where',
                    'className'  => 'App\Models\User',
                    'arguments'  => [
                        'type'                => 'arguments',
                        'autocompletingIndex' => 2,
                        'children'            => [
                            [
                                'type'     => 'argument',
                                'name'     => null,
                                'children' => [
                                    [
                                        'type'  => 'string',
                                        'value' => 'email',
                                    ],
                                ],
                            ],
                            [
                                'type'     => 'argument',
                                'name'     => null,
                                'children' => [
                                    [
                                        'type'  => 'string',
                                        'value' => '',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'children' => [],
                ],
            ],
        ],
    ]));
});

test('basic method', function () {
    expect(contextResult('basic-method'))->toBe(createContext([
        [
            'type'  => 'assignment',
            'name'  => 'user',
            'value' => [
                [
                    'type'      => 'object',
                    'className' => 'App\Models\User',
                    'arguments' => [
                        'type'                => 'arguments',
                        'autocompletingIndex' => 0,
                        'children'            => [],
                    ],
                    'children' => [],
                ],
            ],
        ],
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'where',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 0,
                'children'            => [],
            ],
            'children' => [],
        ],
    ]));
});

test('basic method with params', function () {
    expect(contextResult('basic-method-with-params'))->toBe(createContext([
        [
            'type'  => 'assignment',
            'name'  => 'user',
            'value' => [
                [
                    'type'      => 'object',
                    'className' => 'App\Models\User',
                    'arguments' => [
                        'type'                => 'arguments',
                        'autocompletingIndex' => 0,
                        'children'            => [],
                    ],
                    'children' => [],
                ],
            ],
        ],
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'where',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 1,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'  => 'string',
                                'value' => 'email',
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('chained method with params', function () {
    expect(contextResult('chained-method-with-params'))->toBe(createContext([
        [
            'type'  => 'assignment',
            'name'  => 'user',
            'value' => [
                [
                    'type'      => 'object',
                    'className' => 'App\Models\User',
                    'arguments' => [
                        'type'                => 'arguments',
                        'autocompletingIndex' => 0,
                        'children'            => [],
                    ],
                    'children' => [],
                ],
            ],
        ],
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'orWhere',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 1,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'  => 'string',
                                'value' => 'name',
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [
                [
                    'type'       => 'methodCall',
                    'methodName' => 'where',
                    'className'  => 'App\Models\User',
                    'arguments'  => [
                        'type'                => 'arguments',
                        'autocompletingIndex' => 2,
                        'children'            => [
                            [
                                'type'     => 'argument',
                                'name'     => null,
                                'children' => [
                                    [
                                        'type'  => 'string',
                                        'value' => 'email',
                                    ],
                                ],
                            ],
                            [
                                'type'     => 'argument',
                                'name'     => null,
                                'children' => [
                                    [
                                        'type'  => 'string',
                                        'value' => '',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'children' => [],
                ],
            ],
        ],
    ]));
});

test('anonymous function as param', function () {
    expect(contextResult('anonymous-function-param'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'where',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 1,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'       => 'closure',
                                'parameters' => [
                                    [
                                        'types' => ['Illuminate\Database\Query\Builder'],
                                        'name'  => 'q',
                                    ],
                                ],
                                'children' => [
                                    [
                                        'type'           => 'methodCall',
                                        'autocompleting' => true,
                                        'methodName'     => 'whereIn',
                                        'className'      => 'Illuminate\Database\Query\Builder',
                                        'arguments'      => [
                                            'type'                => 'arguments',
                                            'autocompletingIndex' => 0,
                                            'children'            => [],
                                        ],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('arrow function as param', function () {
    expect(contextResult('arrow-function-param'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'where',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 1,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'       => 'closure',
                                'parameters' => [
                                    [
                                        'types' => ['Illuminate\Database\Query\Builder'],
                                        'name'  => 'q',
                                    ],
                                ],
                                'children' => [
                                    [
                                        'type'           => 'methodCall',
                                        'autocompleting' => true,
                                        'methodName'     => 'whereIn',
                                        'className'      => 'Illuminate\Database\Query\Builder',
                                        'arguments'      => [
                                            'type'                => 'arguments',
                                            'autocompletingIndex' => 0,
                                            'children'            => [],
                                        ],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('nested functions', function () {
    expect(contextResult('nested'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'get',
            'className'      => 'Route',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 2,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'  => 'string',
                                'value' => '/',
                            ],
                        ],
                    ],
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'       => 'closure',
                                'parameters' => [],
                                'children'   => [
                                    [
                                        'type'       => 'methodCall',
                                        'methodName' => 'trans',
                                        'className'  => null,
                                        'arguments'  => [
                                            'type'                => 'arguments',
                                            'autocompletingIndex' => 1,
                                            'children'            => [
                                                [
                                                    'type'     => 'argument',
                                                    'name'     => null,
                                                    'children' => [
                                                        [
                                                            'type'  => 'string',
                                                            'value' => 'auth.throttle',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'children' => [],
                                    ],
                                    [
                                        'type'           => 'methodCall',
                                        'autocompleting' => true,
                                        'methodName'     => 'where',
                                        'className'      => 'App\Models\User',
                                        'arguments'      => [
                                            'type'                => 'arguments',
                                            'autocompletingIndex' => 0,
                                            'children'            => [],
                                        ],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('array with arrow function', function () {
    expect(contextResult('array-with-arrow-function'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'with',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 0,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'           => 'array',
                                'autocompleting' => true,
                                'children'       => [
                                    [
                                        'type' => 'array_item',
                                        'key'  => [
                                            'type'  => 'string',
                                            'value' => 'team',
                                        ],
                                        'value' => [
                                            'type'       => 'closure',
                                            'parameters' => [
                                                [
                                                    'types' => ['Illuminate\Database\Query\Builder'],
                                                    'name'  => 'q',
                                                ],
                                            ],
                                            'children' => [
                                                [
                                                    'type'           => 'methodCall',
                                                    'autocompleting' => true,
                                                    'methodName'     => 'where',
                                                    'className'      => 'Illuminate\Database\Query\Builder',
                                                    'arguments'      => [
                                                        'type'                => 'arguments',
                                                        'autocompletingIndex' => 0,
                                                        'children'            => [],
                                                    ],
                                                    'children' => [],
                                                ],
                                            ],
                                        ],
                                        'autocompletingValue' => true,
                                    ],
                                ],
                                'autocompletingKey'   => false,
                                'autocompletingValue' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('array with arrow function several keys', function () {
    expect(contextResult('array-with-arrow-function-several-keys'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'with',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 0,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'           => 'array',
                                'autocompleting' => true,
                                'children'       => [
                                    [
                                        'type' => 'array_item',
                                        'key'  => [
                                            'type'  => 'string',
                                            'value' => 'team',
                                        ],
                                        'value' => [
                                            'type'       => 'closure',
                                            'parameters' => [
                                                [
                                                    'types' => ['Illuminate\Database\Query\Builder'],
                                                    'name'  => 'q',
                                                ],
                                            ],
                                            'children' => [
                                                [
                                                    'type'       => 'methodCall',
                                                    'methodName' => 'where',
                                                    'className'  => 'Illuminate\Database\Query\Builder',
                                                    'arguments'  => [
                                                        'type'                => 'arguments',
                                                        'autocompletingIndex' => 2,
                                                        'children'            => [
                                                            [
                                                                'type'     => 'argument',
                                                                'name'     => null,
                                                                'children' => [
                                                                    [
                                                                        'type'  => 'string',
                                                                        'value' => '',
                                                                    ],
                                                                ],
                                                            ],
                                                            [
                                                                'type'     => 'argument',
                                                                'name'     => null,
                                                                'children' => [
                                                                    [
                                                                        'type'  => 'string',
                                                                        'value' => '',
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                    'children' => [],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'array_item',
                                        'key'  => [
                                            'type'  => 'string',
                                            'value' => 'organization',
                                        ],
                                        'value' => [
                                            'type'       => 'closure',
                                            'parameters' => [
                                                [
                                                    'types' => [],
                                                    'name'  => 'q',
                                                ],
                                            ],
                                            'children' => [
                                                [
                                                    'type'           => 'methodCall',
                                                    'autocompleting' => true,
                                                    'methodName'     => 'whereIn',
                                                    'className'      => null,
                                                    'arguments'      => [
                                                        'type'                => 'arguments',
                                                        'autocompletingIndex' => 0,
                                                        'children'            => [],
                                                    ],
                                                    'children' => [],
                                                ],
                                            ],
                                        ],
                                        'autocompletingValue' => true,
                                    ],
                                ],
                                'autocompletingKey'   => false,
                                'autocompletingValue' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('eloquent make from set variable', function () {
    expect(contextResult('eloquent-make-from-set-variable'))->toBe(createContext([
        [
            'type'       => 'classDefinition',
            'className'  => 'App\Http\Controllers\ProviderController',
            'extends'    => 'App\Http\Controllers\Controller',
            'implements' => [],
            'properties' => [],
            'children'   => [
                [
                    'type'       => 'methodDefinition',
                    'methodName' => 'store',
                    'parameters' => [
                        [
                            'types' => ['Illuminate\Http\Request'],
                            'name'  => 'request',
                        ],
                    ],
                    'children' => [
                        [
                            'type'  => 'assignment',
                            'name'  => 'provider',
                            'value' => [
                                [
                                    'type'           => 'methodCall',
                                    'autocompleting' => true,
                                    'methodName'     => 'make',
                                    'className'      => 'App\Models\Provider',
                                    'arguments'      => [
                                        'type'                => 'arguments',
                                        'autocompletingIndex' => 0,
                                        'children'            => [
                                            [
                                                'type'     => 'argument',
                                                'name'     => null,
                                                'children' => [
                                                    [
                                                        'type'                => 'array',
                                                        'autocompleting'      => true,
                                                        'children'            => [],
                                                        'autocompletingKey'   => true,
                                                        'autocompletingValue' => true,
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]));
});

test('array with arrow function several keys and second param', function () {
    expect(contextResult('array-with-arrow-function-several-keys-and-second-param'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'with',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 0,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'           => 'array',
                                'autocompleting' => true,
                                'children'       => [
                                    [
                                        'type' => 'array_item',
                                        'key'  => [
                                            'type'  => 'string',
                                            'value' => 'team',
                                        ],
                                        'value' => [
                                            'type'       => 'closure',
                                            'parameters' => [
                                                [
                                                    'types' => ['Illuminate\Database\Query\Builder'],
                                                    'name'  => 'q',
                                                ],
                                            ],
                                            'children' => [
                                                [
                                                    'type'       => 'methodCall',
                                                    'methodName' => 'where',
                                                    'className'  => 'Illuminate\Database\Query\Builder',
                                                    'arguments'  => [
                                                        'type'                => 'arguments',
                                                        'autocompletingIndex' => 2,
                                                        'children'            => [
                                                            [
                                                                'type'     => 'argument',
                                                                'name'     => null,
                                                                'children' => [
                                                                    [
                                                                        'type'  => 'string',
                                                                        'value' => '',
                                                                    ],
                                                                ],
                                                            ],
                                                            [
                                                                'type'     => 'argument',
                                                                'name'     => null,
                                                                'children' => [
                                                                    [
                                                                        'type'  => 'string',
                                                                        'value' => '',
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                    'children' => [],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'array_item',
                                        'key'  => [
                                            'type'  => 'string',
                                            'value' => 'organization',
                                        ],
                                        'value' => [
                                            'type'       => 'closure',
                                            'parameters' => [
                                                [
                                                    'types' => [],
                                                    'name'  => 'q',
                                                ],
                                            ],
                                            'children' => [
                                                [
                                                    'type'           => 'methodCall',
                                                    'autocompleting' => true,
                                                    'methodName'     => 'whereIn',
                                                    'className'      => null,
                                                    'arguments'      => [
                                                        'type'                => 'arguments',
                                                        'autocompletingIndex' => 1,
                                                        'children'            => [
                                                            [
                                                                'type'     => 'argument',
                                                                'name'     => null,
                                                                'children' => [
                                                                    [
                                                                        'type'  => 'string',
                                                                        'value' => '',
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                    'children' => [],
                                                ],
                                            ],
                                        ],
                                        'autocompletingValue' => true,
                                    ],
                                ],
                                'autocompletingKey'   => false,
                                'autocompletingValue' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('array with arrow function missing second key', function () {
    expect(contextResult('array-with-arrow-function-missing-second-key'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'methodName'     => 'with',
            'className'      => 'App\Models\User',
            'arguments'      => [
                'type'                => 'arguments',
                'autocompletingIndex' => 0,
                'children'            => [
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [
                            [
                                'type'           => 'array',
                                'autocompleting' => true,
                                'children'       => [
                                    [
                                        'type' => 'array_item',
                                        'key'  => [
                                            'type'  => 'string',
                                            'value' => 'team',
                                        ],
                                        'value' => [
                                            'type'       => 'closure',
                                            'parameters' => [
                                                [
                                                    'types' => ['Illuminate\Database\Query\Builder'],
                                                    'name'  => 'q',
                                                ],
                                            ],
                                            'children' => [
                                                [
                                                    'type'       => 'methodCall',
                                                    'methodName' => 'where',
                                                    'className'  => 'Illuminate\Database\Query\Builder',
                                                    'arguments'  => [
                                                        'type'                => 'arguments',
                                                        'autocompletingIndex' => 2,
                                                        'children'            => [
                                                            [
                                                                'type'     => 'argument',
                                                                'name'     => null,
                                                                'children' => [
                                                                    [
                                                                        'type'  => 'string',
                                                                        'value' => '',
                                                                    ],
                                                                ],
                                                            ],
                                                            [
                                                                'type'     => 'argument',
                                                                'name'     => null,
                                                                'children' => [
                                                                    [
                                                                        'type'  => 'string',
                                                                        'value' => '',
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                    'children' => [],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'autocompletingKey'   => true,
                                'autocompletingValue' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'children' => [],
        ],
    ]));
});

test('this reference', function () {
    expect(contextResult('this-reference'))->toBe(createContext([
        [
            'type'       => 'classDefinition',
            'className'  => 'App\Commands\MyCommand',
            'extends'    => 'Vendor\Package\Thing',
            'implements' => ['Vendor\Package\Contracts\BigContract', 'Vendor\Package\Support\Contracts\SmallContract'],
            'properties' => [
                [
                    'types' => ['App\Models\User'],
                    'name'  => 'user',
                ],
            ],
            'children' => [
                [
                    'type'       => 'methodDefinition',
                    'methodName' => 'render',
                    'parameters' => [
                        [
                            'types' => ['array'],
                            'name'  => 'params',
                        ],
                    ],
                    'children' => [
                        [
                            'type'           => 'methodCall',
                            'autocompleting' => true,
                            'methodName'     => 'where',
                            'className'      => 'App\Models\User',
                            'arguments'      => [
                                'type'                => 'arguments',
                                'autocompletingIndex' => 1,
                                'children'            => [
                                    [
                                        'type'     => 'argument',
                                        'name'     => null,
                                        'children' => [
                                            [
                                                'type'  => 'string',
                                                'value' => 'url',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'children' => [
                                [
                                    'type'       => 'methodCall',
                                    'methodName' => 'user',
                                    'className'  => 'App\Models\User',
                                    'arguments'  => [
                                        'type'                => 'arguments',
                                        'autocompletingIndex' => 0,
                                        'children'            => [],
                                    ],
                                    'children' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]));
});

test('object instantiation')->todo();
