<?php

declare(strict_types=1);

namespace App\Lsp\Features\BladeDirectives;

use App\Lsp\Contracts\CompletionProvider;
use App\Lsp\Document;
use App\Lsp\Project;

class BladeDirectiveCompletionProvider implements CompletionProvider
{
    /**
     * Create a new Blade directive completion provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide Blade directive completions for the given document and position.
     *
     * @param  array<string, mixed>  $position
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, array $position): array
    {
        if (!str_ends_with($document->uri, '.blade.php')) {
            return [];
        }

        $range = $this->replacementRange($document, $position);

        if ($range === null) {
            return [];
        }

        return collect($this->defaultDirectives())
            ->map(fn (string|array $snippet, string $label): array => $this->completionItem(
                $label,
                is_array($snippet) ? implode("\n", $snippet) : $snippet,
                $range,
            ))
            ->merge($this->customDirectiveCompletions($range))
            ->values()
            ->all();
    }

    /**
     * Create a Blade directive completion item.
     *
     * @param  array<string, array<string, int>>  $range
     * @return array<string, mixed>
     */
    protected function completionItem(string $label, string $snippet, array $range): array
    {
        return [
            'label'            => $label,
            'kind'             => 14,
            'textEdit'         => [
                'range'   => $range,
                'newText' => $snippet,
            ],
            'insertTextFormat' => 2,
        ];
    }

    /**
     * Get custom Blade directive completion items.
     *
     * @param  array<string, array<string, int>>  $range
     * @return array<int, array<string, mixed>>
     */
    protected function customDirectiveCompletions(array $range): array
    {
        return collect($this->project->index->customBladeDirectives())
            ->map(fn (array $directive): array => $this->completionItem(
                '@' . $directive['name'] . ($directive['hasParams'] ? '(...)' : ''),
                '@' . $directive['name'] . ($directive['hasParams'] ? '(${1})' : ''),
                $range,
            ))
            ->values()
            ->all();
    }

    /**
     * Get the range that should be replaced by the completion.
     *
     * @param  array<string, mixed>  $position
     * @return array{start: array{line: int, character: int}, end: array{line: int, character: int}}|null
     */
    protected function replacementRange(Document $document, array $position): ?array
    {
        $lineNumber = $position['line'] ?? null;
        $character = $position['character'] ?? null;

        if (!is_int($lineNumber) || !is_int($character)) {
            return null;
        }

        $line = explode("\n", $document->content)[$lineNumber] ?? '';
        $text = substr($line, 0, $character);

        preg_match('/[\w\d\-_\.\:\\\\\/@]+$/', $text, $matches);

        $token = $matches[0] ?? '';

        if (!str_starts_with($token, '@')) {
            return null;
        }

        return [
            'start' => [
                'line'      => $lineNumber,
                'character' => $character - strlen($token),
            ],
            'end' => [
                'line'      => $lineNumber,
                'character' => $character,
            ],
        ];
    }

    /**
     * Get the default Blade directive snippets.
     *
     * @return array<string, string|array<int, string>>
     */
    protected function defaultDirectives(): array
    {
        return [
            '@if(...)'                      => ['@if (${1})', "\t" . '${2}', '@endif'],
            '@error(...)'                   => ['@error(${1})', "\t" . '${2}', '@enderror'],
            '@if(...) ... @else ... @endif' => ['@if (${1})', "\t" . '${2}', '@else', "\t" . '${3}', '@endif'],
            '@foreach(...)'                 => ['@foreach (${1} as ${2})', "\t" . '${3}', '@endforeach'],
            '@forelse(...)'                 => ['@forelse (${1} as ${2})', "\t" . '${3}', '@empty', "\t" . '${4}', '@endforelse'],
            '@for(...)'                     => ['@for (${1})', "\t" . '${2}', '@endfor'],
            '@while(...)'                   => ['@while (${1})', "\t" . '${2}', '@endwhile'],
            '@switch(...)'                  => ['@switch(${1})', "\t" . '@case(${2})', "\t\t" . '${3}', "\t\t" . '@break', '', "\t" . '@default', "\t\t" . '${4}', '@endswitch'],
            '@case(...)'                    => ['@case(${1})', "\t" . '${2}', '@break'],
            '@break'                        => '@break',
            '@continue'                     => '@continue',
            '@break(...)'                   => '@break(${1})',
            '@continue(...)'                => '@continue(${1})',
            '@default'                      => '@default',
            '@extends(...)'                 => '@extends(${1})',
            '@empty'                        => '@empty',
            '@verbatim ...'                 => ['@verbatim', "\t" . '${1}', '@endverbatim'],
            '@json(...)'                    => '@json(${1})',
            '@elseif (...)'                 => '@elseif (${1})',
            '@else'                         => '@else',
            '@unless(...)'                  => ['@unless (${1})', "\t" . '${2}', '@endunless'],
            '@isset(...)'                   => ['@isset(${1})', "\t" . '${2}', '@endisset'],
            '@empty(...)'                   => ['@empty(${1})', "\t" . '${2}', '@endempty'],
            '@auth'                         => ['@auth', "\t" . '${1}', '@endauth'],
            '@guest'                        => ['@guest', "\t" . '${1}', '@endguest'],
            '@auth(...)'                    => ['@auth(${1})', "\t" . '${2}', '@endauth'],
            '@guest(...)'                   => ['@guest(${1})', "\t" . '${2}', '@endguest'],
            '@can(...)'                     => ['@can(${1})', "\t" . '${2}', '@endcan'],
            '@cannot(...)'                  => ['@cannot(${1})', "\t" . '${2}', '@endcannot'],
            '@elsecan(...)'                 => '@elsecan(${1})',
            '@elsecannot(...)'              => '@elsecannot(${1})',
            '@production'                   => ['@production', "\t" . '${1}', '@endproduction'],
            '@env(...)'                     => ['@env(${1})', "\t" . '${2}', '@endenv'],
            '@hasSection(...)'              => ['@hasSection(${1})', "\t" . '${2}', '@endif'],
            '@sectionMissing(...)'          => ['@sectionMissing(${1})', '${2}', '@endif'],
            '@include(...)'                 => '@include(${1})',
            '@includeIf(...)'               => '@includeIf(${1})',
            '@includeWhen(...)'             => '@includeWhen(${1}, ${2})',
            '@includeUnless(...)'           => '@includeUnless(${1}, ${2})',
            '@includeFirst(...)'            => '@includeFirst(${1})',
            '@each(...)'                    => '@each(${1}, ${2}, ${3})',
            '@once'                         => ['@once', "\t" . '${1}', '@endonce'],
            '@yield(...)'                   => '@yield(${1})',
            '@slot(...)'                    => '@slot(${1})',
            '@stack(...)'                   => '@stack(${1})',
            '@push(...)'                    => ['@push(${1})', "\t" . '${2}', '@endpush'],
            '@pushIf(...)'                  => ['@pushIf(${1})', "\t" . '${2}', '@endPushIf'],
            '@pushOnce(...)'                => ['@pushOnce(${1})', "\t" . '${2}', '@endPushOnce'],
            '@prepend(...)'                 => ['@prepend(${1})', "\t" . '${2}', '@endprepend'],
            '@php'                          => ['@php', "\t" . '${1}', '@endphp'],
            '@component(...)'               => ['@component(${1})', '${2}', '@endcomponent'],
            '@section(...) ... @endsection' => ['@section(${1})', '${2}', '@endsection'],
            '@section(...)'                 => '@section(${1})',
            '@props(...)'                   => '@props(${1})',
            '@use(...)'                     => '@use(${1})',
            '@show'                         => '@show',
            '@stop'                         => '@stop',
            '@parent'                       => '@parent',
            '@csrf'                         => '@csrf',
            '@method(...)'                  => '@method(${1})',
            '@inject(...)'                  => '@inject(${1}, ${2})',
            '@dump(...)'                    => '@dump(${1})',
            '@dd(...)'                      => '@dd(${1})',
            '@lang(...)'                    => '@lang(${1})',
            '@endif'                        => '@endif',
            '@enderror'                     => '@enderror',
            '@endforeach'                   => '@endforeach',
            '@endforelse'                   => '@endforelse',
            '@endfor'                       => '@endfor',
            '@endwhile'                     => '@endwhile',
            '@endswitch'                    => '@endswitch',
            '@endverbatim'                  => '@endverbatim',
            '@endunless'                    => '@endunless',
            '@endisset'                     => '@endisset',
            '@endempty'                     => '@endempty',
            '@endauth'                      => '@endauth',
            '@endguest'                     => '@endguest',
            '@endproduction'                => '@endproduction',
            '@endenv'                       => '@endenv',
            '@endonce'                      => '@endonce',
            '@endpush'                      => '@endpush',
            '@endpushIf'                    => '@endPushIf',
            '@endpushOnce'                  => '@endPushOnce',
            '@endprepend'                   => '@endprepend',
            '@endphp'                       => '@endphp',
            '@endcomponent'                 => '@endcomponent',
            '@endsection'                   => '@endsection',
            '@endslot'                      => '@endslot',
            '@endcan'                       => '@endcan',
            '@endcannot'                    => '@endcannot',
        ];
    }
}
