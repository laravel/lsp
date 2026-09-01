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

function contextFromArray($values)
{
    return array_merge([
        'classDefinition'        => null,
        'implements'             => [],
        'extends'                => null,
        'methodDefinition'       => null,
        'methodDefinitionParams' => [],
        'methodExistingArgs'     => [],
        'classUsed'              => null,
        'methodUsed'             => null,
        'parent'                 => null,
        'variables'              => [],
        'definedProperties'      => [],
        'fillingInArrayKey'      => false,
        'fillingInArrayValue'    => false,
        'paramIndex'             => 0,
    ], $values);
}

function contextResult($file, $dump = false)
{
    $code = fromFile($file);

    return contextResultCode($code, $dump);
}

function contextResultCode($code, $dump = false)
{
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
            'name'           => 'render',
            'class'          => null,
            'arguments'      => [],
            'children'       => [],
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
            'name'           => 'render',
            'class'          => null,
            'arguments'      => [
                [
                    'type'  => 'string',
                    'value' => 'my-view',
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
            'name'           => 'where',
            'class'          => 'App\Models\User',
            'arguments'      => [],
            'children'       => [],
        ],
    ]));
});

test('basic static method with params', function () {
    expect(contextResult('basic-static-method-with-params'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'name'           => 'where',
            'class'          => 'App\Models\User',
            'arguments'      => [
                [
                    'type'  => 'string',
                    'value' => 'email',
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
            'name'           => 'orWhere',
            'class'          => 'App\Models\User',
            'arguments'      => [
                [
                    'type'  => 'string',
                    'value' => 'name',
                ],
            ],
            'children' => [
                [
                    'type'      => 'methodCall',
                    'name'      => 'where',
                    'class'     => 'App\Models\User',
                    'arguments' => [
                        [
                            'type'  => 'string',
                            'value' => 'email',
                        ],
                        [
                            'type'  => 'string',
                            'value' => '',
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
                    'type'     => 'object',
                    'name'     => 'App\Models\User',
                    'children' => [],
                ],
            ],
        ],
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'name'           => 'where',
            'class'          => 'App\Models\User',
            'arguments'      => [],
            'children'       => [],
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
                    'type'     => 'object',
                    'name'     => 'App\Models\User',
                    'children' => [],
                ],
            ],
        ],
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'name'           => 'where',
            'class'          => 'App\Models\User',
            'arguments'      => [
                [
                    'type'  => 'string',
                    'value' => 'email',
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
                    'type'     => 'object',
                    'name'     => 'App\Models\User',
                    'children' => [],
                ],
            ],
        ],
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'name'           => 'orWhere',
            'class'          => 'App\Models\User',
            'arguments'      => [
                [
                    'type'  => 'string',
                    'value' => 'name',
                ],
            ],
            'children' => [
                [
                    'type'      => 'methodCall',
                    'name'      => 'where',
                    'class'     => 'App\Models\User',
                    'arguments' => [
                        [
                            'type'  => 'string',
                            'value' => 'email',
                        ],
                        [
                            'type'  => 'string',
                            'value' => '',
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
            'name'           => 'where',
            'class'          => 'App\Models\User',
            'arguments'      => [
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
                            'name'           => 'whereIn',
                            'class'          => 'Illuminate\Database\Query\Builder',
                            'arguments'      => [],
                            'children'       => [],
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
            'name'           => 'where',
            'class'          => 'App\Models\User',
            'arguments'      => [
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
                            'name'           => 'whereIn',
                            'class'          => 'Illuminate\Database\Query\Builder',
                            'arguments'      => [],
                            'children'       => [],
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
            'name'           => 'get',
            'class'          => 'Route',
            'arguments'      => [
                [
                    'type'  => 'string',
                    'value' => '/',
                ],
                [
                    'type'       => 'closure',
                    'parameters' => [],
                    'children'   => [
                        [
                            'type'      => 'methodCall',
                            'name'      => 'trans',
                            'class'     => null,
                            'arguments' => [
                                [
                                    'type'  => 'string',
                                    'value' => 'auth.throttle',
                                ],
                            ],
                            'children' => [],
                        ],
                        [
                            'type'           => 'methodCall',
                            'autocompleting' => true,
                            'name'           => 'where',
                            'class'          => 'App\Models\User',
                            'arguments'      => [],
                            'children'       => [],
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
            'name'           => 'with',
            'class'          => 'App\Models\User',
            'arguments'      => [
                [
                    'type'           => 'array',
                    'autocompleting' => true,
                    'children'       => [
                        [
                            'key' => [
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
                                        'name'           => 'where',
                                        'class'          => 'Illuminate\Database\Query\Builder',
                                        'arguments'      => [],
                                        'children'       => [],
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
            'children' => [],
        ],
    ]));
});

test('array with arrow function several keys', function () {
    expect(contextResult('array-with-arrow-function-several-keys'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'name'           => 'with',
            'class'          => 'App\Models\User',
            'arguments'      => [
                [
                    'type'           => 'array',
                    'autocompleting' => true,
                    'children'       => [
                        [
                            'key' => [
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
                                        'type'      => 'methodCall',
                                        'name'      => 'where',
                                        'class'     => 'Illuminate\Database\Query\Builder',
                                        'arguments' => [
                                            [
                                                'type'  => 'string',
                                                'value' => '',
                                            ],
                                            [
                                                'type'  => 'string',
                                                'value' => '',
                                            ],
                                        ],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key' => [
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
                                        'name'           => 'whereIn',
                                        'class'          => null,
                                        'arguments'      => [],
                                        'children'       => [],
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
            'children' => [],
        ],
    ]));
});

test('eloquent make from set variable', function () {
    expect(contextResult('eloquent-make-from-set-variable'))->toBe(createContext([
        [
            'type'       => 'classDefinition',
            'name'       => 'App\Http\Controllers\ProviderController',
            'extends'    => 'App\Http\Controllers\Controller',
            'implements' => [],
            'properties' => [],
            'children'   => [
                [
                    'type'       => 'methodDefinition',
                    'name'       => 'store',
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
                                    'name'           => 'make',
                                    'class'          => 'App\Models\Provider',
                                    'arguments'      => [
                                        [
                                            'type'                => 'array',
                                            'autocompleting'      => true,
                                            'children'            => [],
                                            'autocompletingKey'   => true,
                                            'autocompletingValue' => true,
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
            'name'           => 'with',
            'class'          => 'App\Models\User',
            'arguments'      => [
                [
                    'type'           => 'array',
                    'autocompleting' => true,
                    'children'       => [
                        [
                            'key' => [
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
                                        'type'      => 'methodCall',
                                        'name'      => 'where',
                                        'class'     => 'Illuminate\Database\Query\Builder',
                                        'arguments' => [
                                            [
                                                'type'  => 'string',
                                                'value' => '',
                                            ],
                                            [
                                                'type'  => 'string',
                                                'value' => '',
                                            ],
                                        ],
                                        'children' => [],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'key' => [
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
                                        'name'           => 'whereIn',
                                        'class'          => null,
                                        'arguments'      => [
                                            [
                                                'type'  => 'string',
                                                'value' => '',
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
            'children' => [],
        ],
    ]));
});

test('array with arrow function missing second key', function () {
    expect(contextResult('array-with-arrow-function-missing-second-key'))->toBe(createContext([
        [
            'type'           => 'methodCall',
            'autocompleting' => true,
            'name'           => 'with',
            'class'          => 'App\Models\User',
            'arguments'      => [
                [
                    'type'           => 'array',
                    'autocompleting' => true,
                    'children'       => [
                        [
                            'key' => [
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
                                        'type'      => 'methodCall',
                                        'name'      => 'where',
                                        'class'     => 'Illuminate\Database\Query\Builder',
                                        'arguments' => [
                                            [
                                                'type'  => 'string',
                                                'value' => '',
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
            'children' => [],
        ],
    ]));
});

test('this reference', function () {
    expect(contextResult('this-reference'))->toBe(createContext([
        [
            'type'       => 'classDefinition',
            'name'       => 'App\Commands\MyCommand',
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
                    'name'       => 'render',
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
                            'name'           => 'where',
                            'class'          => 'App\Models\User',
                            'arguments'      => [
                                [
                                    'type'  => 'string',
                                    'value' => 'url',
                                ],
                            ],
                            'children' => [
                                [
                                    'type'      => 'methodCall',
                                    'name'      => 'user',
                                    'class'     => 'App\Models\User',
                                    'arguments' => [],
                                    'children'  => [],
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

describe('blade directives', function () {
    test('directive with open quote', function () {
        expect(contextResultCode("@can('"))->toBe(createContext([
            [
                'type'     => 'blade',
                'children' => [
                    [
                        'type'           => 'methodCall',
                        'autocompleting' => true,
                        'methodName'     => '@can',
                        'className'      => null,
                        'arguments'      => [
                            'type'                => 'arguments',
                            'autocompletingIndex' => 0,
                            'children'            => [],
                        ],
                        'children'       => [],
                    ]
                ]
            ]
        ]));
    });

    test('directive with an argument', function () {
        expect(contextResultCode("@include('a.b'"))->toBe(createContext([
            [
                'type'     => 'blade',
                'children' => [
                    [
                        'type'           => 'methodCall',
                        'autocompleting' => true,
                        'methodName'     => '@include',
                        'className'      => null,
                        'arguments'      => [
                            'type'                => 'arguments',
                            'autocompletingIndex' => 1,
                            'children'            => [
                                [
                                    'type'  => 'argument',
                                    'name'  => null,
                                    'children' => [
                                        [
                                            'type'  => 'string',
                                            'value' => 'a.b',
                                        ],
                                    ],
                                ]
                            ],
                        ],
                        'children'       => [],
                    ]
                ]
            ]
        ]));
    });

    test('basic directive', function (string $code) {
        expect(contextResultCode($code))->toBe(createContext([]));
    })->with([
        '@csrf',
        '@endif',
    ]);
});
