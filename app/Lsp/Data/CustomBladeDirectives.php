<?php

declare(strict_types=1);

namespace App\Lsp\Data;

use App\Lsp\Contracts\DataProvider;
use App\Lsp\Project;

class CustomBladeDirectives implements DataProvider
{
    /**
     * Instantiate a new class instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get the custom Blade directives template to run.
     */
    public function template(): string
    {
        return file_get_contents(__DIR__ . '/Templates/blade-directives.php') ?: '';
    }

    /**
     * Parse the raw custom Blade directive data.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array<int, array{name: string, hasParams: bool}>
     */
    public function parse(array $data): array
    {
        return collect($data)
            ->filter(fn (mixed $directive): bool => is_array($directive) && is_string($directive['name'] ?? null))
            ->map(fn (array $directive): array => [
                'name'      => $directive['name'],
                'hasParams' => (bool) ($directive['hasParams'] ?? false),
            ])
            ->values()
            ->all();
    }

    /**
     * Get data.
     *
     * @return array<int, array{name: string, hasParams: bool}>
     */
    public function get(): array
    {
        $data = $this->project->scripts->json($this->template());

        return $this->parse(is_array($data) ? $data : []);
    }

    /**
     * Get custom Blade directive-related watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        return [
            'app/{,*,**/*}Provider.php',
        ];
    }
}
