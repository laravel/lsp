<?php

use App\Parser\DetectWalker;

function detect($values)
{
    return json_encode($values, JSON_PRETTY_PRINT);
}

function detectFromArray($values)
{
    return array_map(fn ($v) => array_merge([
        'class'  => null,
        'method' => null,
        'params' => [],
    ], $v), $values);
}

function result($file)
{
    $code = fromFile($file);
    $walker = new DetectWalker($code);

    $context = $walker->walk();

    return $context->toJson(JSON_PRETTY_PRINT);
}

test('extract functions and string params', function () {
    expect(result('detect/routes'))->toBe(detect([

        [
            [
                'method' => 'basicFunc',
                'class'  => null,
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'whatever',
                        'start' => [
                            'line'   => 9,
                            'column' => 10,
                        ],
                        'end' => [
                            'line'   => 9,
                            'column' => 18,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'name',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'home.show',
                        'start' => [
                            'line'   => 11,
                            'column' => 55,
                        ],
                        'end' => [
                            'line'   => 11,
                            'column' => 64,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'get',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => '/',
                        'start' => [
                            'line'   => 11,
                            'column' => 11,
                        ],
                        'end' => [
                            'line'   => 11,
                            'column' => 12,
                        ],
                    ],
                    [
                        'type'  => 'array',
                        'value' => [
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'unknown',
                                    'value' => 'HomeController::class',
                                ],
                            ],
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'string',
                                    'value' => 'show',
                                    'start' => [
                                        'line'   => 11,
                                        'column' => 40,
                                    ],
                                    'end' => [
                                        'line'   => 11,
                                        'column' => 44,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'method' => 'group',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'      => 'closure',
                        'arguments' => [],
                    ],
                ],
            ],
            [
                'method' => 'middleware',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'signed',
                        'start' => [
                            'line'   => 13,
                            'column' => 18,
                        ],
                        'end' => [
                            'line'   => 13,
                            'column' => 24,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'name',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'profile.edit',
                        'start' => [
                            'line'   => 15,
                            'column' => 68,
                        ],
                        'end' => [
                            'line'   => 15,
                            'column' => 80,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'get',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'profile',
                        'start' => [
                            'line'   => 15,
                            'column' => 15,
                        ],
                        'end' => [
                            'line'   => 15,
                            'column' => 22,
                        ],
                    ],
                    [
                        'type'  => 'array',
                        'value' => [
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'unknown',
                                    'value' => 'ProfileController::class',
                                ],
                            ],
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'string',
                                    'value' => 'edit',
                                    'start' => [
                                        'line'   => 15,
                                        'column' => 53,
                                    ],
                                    'end' => [
                                        'line'   => 15,
                                        'column' => 57,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'method' => 'name',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'profile.edit',
                        'start' => [
                            'line'   => 15,
                            'column' => 68,
                        ],
                        'end' => [
                            'line'   => 15,
                            'column' => 80,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'get',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'profile',
                        'start' => [
                            'line'   => 15,
                            'column' => 15,
                        ],
                        'end' => [
                            'line'   => 15,
                            'column' => 22,
                        ],
                    ],
                    [
                        'type'  => 'array',
                        'value' => [
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'unknown',
                                    'value' => 'ProfileController::class',
                                ],
                            ],
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'string',
                                    'value' => 'edit',
                                    'start' => [
                                        'line'   => 15,
                                        'column' => 53,
                                    ],
                                    'end' => [
                                        'line'   => 15,
                                        'column' => 57,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'method' => 'group',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'      => 'closure',
                        'arguments' => [],
                    ],
                ],
            ],
            [
                'method' => 'middleware',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'array',
                        'value' => [
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'string',
                                    'value' => 'auth',
                                    'start' => [
                                        'line'   => 19,
                                        'column' => 4,
                                    ],
                                    'end' => [
                                        'line'   => 19,
                                        'column' => 8,
                                    ],
                                ],
                            ],
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'string',
                                    'value' => 'verified',
                                    'start' => [
                                        'line'   => 20,
                                        'column' => 4,
                                    ],
                                    'end' => [
                                        'line'   => 20,
                                        'column' => 12,
                                    ],
                                ],
                            ],
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'string',
                                    'value' => 'within-current-organization',
                                    'start' => [
                                        'line'   => 21,
                                        'column' => 4,
                                    ],
                                    'end' => [
                                        'line'   => 21,
                                        'column' => 31,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'method' => 'name',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'dashboard',
                        'start' => [
                            'line'   => 23,
                            'column' => 72,
                        ],
                        'end' => [
                            'line'   => 23,
                            'column' => 81,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'get',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'dashboard',
                        'start' => [
                            'line'   => 23,
                            'column' => 15,
                        ],
                        'end' => [
                            'line'   => 23,
                            'column' => 81,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'name',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'dashboard',
                        'start' => [
                            'line'   => 23,
                            'column' => 72,
                        ],
                        'end' => [
                            'line'   => 23,
                            'column' => 81,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'get',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'dashboard',
                        'start' => [
                            'line'   => 23,
                            'column' => 15,
                        ],
                        'end' => [
                            'line'   => 23,
                            'column' => 81,
                        ],
                    ],
                    [
                        'type'  => 'array',
                        'value' => [
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'unknown',
                                    'value' => 'DashboardController::class',
                                ],
                            ],
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'string',
                                    'value' => 'show',
                                    'start' => [
                                        'line'   => 23,
                                        'column' => 57,
                                    ],
                                    'end' => [
                                        'line'   => 23,
                                        'column' => 61,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'method' => 'name',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'gitlab.webhook.store',
                        'start' => [
                            'line'   => 30,
                            'column' => 11,
                        ],
                        'end' => [
                            'line'   => 30,
                            'column' => 31,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'middleware',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'unknown',
                        'value' => 'VerifyGitLabWebhookRequest::class',
                    ],
                ],
            ],
            [
                'method' => 'withoutMiddleware',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'web',
                        'start' => [
                            'line'   => 28,
                            'column' => 24,
                        ],
                        'end' => [
                            'line'   => 28,
                            'column' => 27,
                        ],
                    ],
                ],
            ],
            [
                'method' => 'post',
                'class'  => 'Illuminate\\Support\\Facades\\Route',
                'params' => [
                    [
                        'type'  => 'string',
                        'value' => 'gitlab/webhook',
                        'start' => [
                            'line'   => 27,
                            'column' => 12,
                        ],
                        'end' => [
                            'line'   => 27,
                            'column' => 26,
                        ],
                    ],
                    [
                        'type'  => 'array',
                        'value' => [
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'unknown',
                                    'value' => 'GitLabWebhookController::class',
                                ],
                            ],
                            [
                                'key' => [
                                    'type'  => 'null',
                                    'value' => null,
                                ],
                                'value' => [
                                    'type'  => 'string',
                                    'value' => 'store',
                                    'start' => [
                                        'line'   => 27,
                                        'column' => 63,
                                    ],
                                    'end' => [
                                        'line'   => 27,
                                        'column' => 68,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],

    ]));
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
