<?php

use App\Parser\DetectWalker;

function detect($values)
{
    return json_encode($values, JSON_PRETTY_PRINT);
}

function result($file)
{
    $code = fromFile($file);
    $walker = new DetectWalker($code);

    $context = $walker->walk();

    return $context->toJson(JSON_PRETTY_PRINT);
}

test('extract functions and string params', function () {
    expect(result('detect/routes'))->toBe(detect(
        [
            [
                'type'       => 'methodCall',
                'methodName' => 'name',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => 'home.show',
                                    'start' => [
                                        'line'   => 4,
                                        'column' => 55,
                                    ],
                                    'end' => [
                                        'line'   => 4,
                                        'column' => 64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'get',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => '/',
                                    'start' => [
                                        'line'   => 4,
                                        'column' => 11,
                                    ],
                                    'end' => [
                                        'line'   => 4,
                                        'column' => 12,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type'     => 'argument',
                            'name'     => null,
                            'children' => [
                                [
                                    'type'     => 'array',
                                    'children' => [
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'       => 'methodCall',
                                                'methodName' => null,
                                                'className'  => 'HomeController',
                                                'arguments'  => [
                                                    'type'                => 'arguments',
                                                    'autocompletingIndex' => 0,
                                                    'children'            => [],
                                                ],
                                                'children' => [],
                                            ],
                                        ],
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'  => 'string',
                                                'value' => 'show',
                                                'start' => [
                                                    'line'   => 4,
                                                    'column' => 40,
                                                ],
                                                'end' => [
                                                    'line'   => 4,
                                                    'column' => 44,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'group',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
                'arguments'  => [
                    'type'                => 'arguments',
                    'autocompletingIndex' => 1,
                    'children'            => [
                        [
                            'type'     => 'argument',
                            'name'     => null,
                            'children' => [
                                [
                                    'type'       => 'closure',
                                    'parameters' => [],
                                    'children'   => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'middleware',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => 'signed',
                                    'start' => [
                                        'line'   => 6,
                                        'column' => 18,
                                    ],
                                    'end' => [
                                        'line'   => 6,
                                        'column' => 24,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'name',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => 'profile.edit',
                                    'start' => [
                                        'line'   => 7,
                                        'column' => 68,
                                    ],
                                    'end' => [
                                        'line'   => 7,
                                        'column' => 80,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'get',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => 'profile',
                                    'start' => [
                                        'line'   => 7,
                                        'column' => 15,
                                    ],
                                    'end' => [
                                        'line'   => 7,
                                        'column' => 22,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type'     => 'argument',
                            'name'     => null,
                            'children' => [
                                [
                                    'type'     => 'array',
                                    'children' => [
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'       => 'methodCall',
                                                'methodName' => null,
                                                'className'  => 'ProfileController',
                                                'arguments'  => [
                                                    'type'                => 'arguments',
                                                    'autocompletingIndex' => 0,
                                                    'children'            => [],
                                                ],
                                                'children' => [],
                                            ],
                                        ],
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'  => 'string',
                                                'value' => 'edit',
                                                'start' => [
                                                    'line'   => 7,
                                                    'column' => 53,
                                                ],
                                                'end' => [
                                                    'line'   => 7,
                                                    'column' => 57,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'group',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
                'arguments'  => [
                    'type'                => 'arguments',
                    'autocompletingIndex' => 1,
                    'children'            => [
                        [
                            'type'     => 'argument',
                            'name'     => null,
                            'children' => [
                                [
                                    'type'       => 'closure',
                                    'parameters' => [],
                                    'children'   => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'middleware',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
                'arguments'  => [
                    'type'                => 'arguments',
                    'autocompletingIndex' => 1,
                    'children'            => [
                        [
                            'type'     => 'argument',
                            'name'     => null,
                            'children' => [
                                [
                                    'type'     => 'array',
                                    'children' => [
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'  => 'string',
                                                'value' => 'auth',
                                                'start' => [
                                                    'line'   => 10,
                                                    'column' => 19,
                                                ],
                                                'end' => [
                                                    'line'   => 10,
                                                    'column' => 23,
                                                ],
                                            ],
                                        ],
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'  => 'string',
                                                'value' => 'verified',
                                                'start' => [
                                                    'line'   => 10,
                                                    'column' => 27,
                                                ],
                                                'end' => [
                                                    'line'   => 10,
                                                    'column' => 35,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'name',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => 'dashboard',
                                    'start' => [
                                        'line'   => 11,
                                        'column' => 72,
                                    ],
                                    'end' => [
                                        'line'   => 11,
                                        'column' => 81,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'get',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => 'dashboard',
                                    'start' => [
                                        'line'   => 11,
                                        'column' => 15,
                                    ],
                                    'end' => [
                                        'line'   => 11,
                                        'column' => 24,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type'     => 'argument',
                            'name'     => null,
                            'children' => [
                                [
                                    'type'     => 'array',
                                    'children' => [
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'       => 'methodCall',
                                                'methodName' => null,
                                                'className'  => 'DashboardController',
                                                'arguments'  => [
                                                    'type'                => 'arguments',
                                                    'autocompletingIndex' => 0,
                                                    'children'            => [],
                                                ],
                                                'children' => [],
                                            ],
                                        ],
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'  => 'string',
                                                'value' => 'show',
                                                'start' => [
                                                    'line'   => 11,
                                                    'column' => 57,
                                                ],
                                                'end' => [
                                                    'line'   => 11,
                                                    'column' => 61,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'name',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => 'gitlab.webhook.store',
                                    'start' => [
                                        'line'   => 17,
                                        'column' => 11,
                                    ],
                                    'end' => [
                                        'line'   => 17,
                                        'column' => 31,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'middleware',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
                'arguments'  => [
                    'type'                => 'arguments',
                    'autocompletingIndex' => 1,
                    'children'            => [
                        [
                            'type'     => 'argument',
                            'name'     => null,
                            'children' => [
                                [
                                    'type'       => 'methodCall',
                                    'methodName' => null,
                                    'className'  => 'VerifyGitLabWebhookRequest',
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
            [
                'type'       => 'methodCall',
                'methodName' => 'post',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => 'gitlab/webhook',
                                    'start' => [
                                        'line'   => 15,
                                        'column' => 11,
                                    ],
                                    'end' => [
                                        'line'   => 15,
                                        'column' => 25,
                                    ],
                                ],
                            ],
                        ],
                        [
                            'type'     => 'argument',
                            'name'     => null,
                            'children' => [
                                [
                                    'type'     => 'array',
                                    'children' => [
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'       => 'methodCall',
                                                'methodName' => null,
                                                'className'  => 'GitLabWebhookController',
                                                'arguments'  => [
                                                    'type'                => 'arguments',
                                                    'autocompletingIndex' => 0,
                                                    'children'            => [],
                                                ],
                                                'children' => [],
                                            ],
                                        ],
                                        [
                                            'type'  => 'array_item',
                                            'key'   => null,
                                            'value' => [
                                                'type'  => 'string',
                                                'value' => 'store',
                                                'start' => [
                                                    'line'   => 15,
                                                    'column' => 62,
                                                ],
                                                'end' => [
                                                    'line'   => 15,
                                                    'column' => 67,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type'       => 'methodCall',
                'methodName' => 'withoutMiddleware',
                'className'  => 'Illuminate\\Support\\Facades\\Route',
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
                                    'value' => 'web',
                                    'start' => [
                                        'line'   => 14,
                                        'column' => 25,
                                    ],
                                    'end' => [
                                        'line'   => 14,
                                        'column' => 28,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]
    ));
});

test('extract component parameter expressions', function () {
    $blade = <<<'BLADE'
    <x-button :href="route('reports.index')" />
    <x-button :href="route('reports.index')">Reports</x-button>
    <flux:button :href="route('reports.show', $report)">Show</flux:button>
    <x-input placeholder="{{ __('reports.search') }}" />
    BLADE;

    $walker = new DetectWalker($blade);

    expect($walker->walk()->toJson(JSON_PRETTY_PRINT))->toBe(detect([
        [
            'type'       => 'methodCall',
            'methodName' => 'route',
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
                                'value' => 'reports.index',
                                'start' => [
                                    'line'   => 0,
                                    'column' => 23,
                                ],
                                'end' => [
                                    'line'   => 0,
                                    'column' => 36,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'type'       => 'methodCall',
            'methodName' => 'route',
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
                                'value' => 'reports.index',
                                'start' => [
                                    'line'   => 1,
                                    'column' => 23,
                                ],
                                'end' => [
                                    'line'   => 1,
                                    'column' => 36,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'type'       => 'methodCall',
            'methodName' => 'route',
            'className'  => null,
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
                                'value' => 'reports.show',
                                'start' => [
                                    'line'   => 2,
                                    'column' => 26,
                                ],
                                'end' => [
                                    'line'   => 2,
                                    'column' => 38,
                                ],
                            ],
                        ],
                    ],
                    [
                        'type'     => 'argument',
                        'name'     => null,
                        'children' => [],
                    ],
                ],
            ],
        ],
        [
            'type'       => 'methodCall',
            'methodName' => '__',
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
                                'value' => 'reports.search',
                                'start' => [
                                    'line'   => 3,
                                    'column' => 28,
                                ],
                                'end' => [
                                    'line'   => 3,
                                    'column' => 42,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]));
});

test('extract component parameter echoes after a line break', function () {
    $blade = <<<'BLADE'
    <x-input class="{{ route('a.b') }}
        {{ route('c.d') }}" />
    BLADE;

    $walker = new DetectWalker($blade);

    $route = fn (string $name, int $line, int $column) => [
        'type'       => 'methodCall',
        'methodName' => 'route',
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
                            'value' => $name,
                            'start' => ['line' => $line, 'column' => $column],
                            'end'   => ['line' => $line, 'column' => $column + mb_strlen($name)],
                        ],
                    ],
                ],
            ],
        ],
    ];

    expect($walker->walk()->toJson(JSON_PRETTY_PRINT))->toBe(detect([
        $route('a.b', 0, 25),
        $route('c.d', 1, 13),
    ]));
});
