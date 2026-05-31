<?php

declare(strict_types=1);

namespace App\Lsp\Features\Env;

use App\Lsp\CodeActions\CodeActionContext;
use App\Lsp\Contracts\CodeActionProvider;
use App\Lsp\Document;
use App\Lsp\Support\FileUri;
use App\Lsp\Workspace;

class EnvCodeActionProvider implements CodeActionProvider
{
    /**
     * Create a new env code action provider instance.
     */
    public function __construct(
        protected Workspace $workspace,
    ) {}

    /**
     * Provide env code actions for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, CodeActionContext $context): array
    {
        if (!$context->accepts('quickfix')) {
            return [];
        }

        $actions = $context->diagnostics('env')
            ->flatMap(fn (array $diagnostic): array => $this->actionsFor($document, $diagnostic))
            ->values()
            ->all();

        $viteAction = $this->viteEnvAction($document, $context);

        if ($viteAction !== null) {
            $actions[] = $viteAction;
        }

        return $actions;
    }

    /**
     * Create the Vite env variable code action.
     *
     * @return array<string, mixed>|null
     */
    protected function viteEnvAction(Document $document, CodeActionContext $context): ?array
    {
        if (!$this->workspace->config->boolean('envViteQuickFix', true) || !$this->isEnvDocument($document)) {
            return null;
        }

        $lines = explode("\n", $document->content);
        $vitePrefix = 'VITE_';
        $envVariables = array_values(array_filter(
            $this->selectedEnvVariables($document, $context->range),
            fn (string $envVariable): bool => !$this->containsEnvVariable($lines, $vitePrefix . $envVariable),
        ));

        if ($envVariables === []) {
            return null;
        }

        [$line, $prefix] = $this->viteInsertionPoint($lines);

        $value = collect($envVariables)
            ->map(fn (string $envVariable): string => "{$vitePrefix}{$envVariable}=\"\${{$envVariable}}\"")
            ->implode("\n");

        return [
            'title' => count($envVariables) === 1
                ? "Create Vite env variable from \"{$envVariables[0]}\""
                : 'Create Vite env variables from selection',
            'kind' => 'quickfix',
            'edit' => [
                'changes' => [
                    $document->uri => [[
                        'range' => [
                            'start' => [
                                'line'      => $line,
                                'character' => 0,
                            ],
                            'end' => [
                                'line'      => $line,
                                'character' => 0,
                            ],
                        ],
                        'newText' => $prefix . $value,
                    ]],
                ],
            ],
            'command' => [
                'title'     => 'Open file',
                'command'   => 'laravel.open',
                'arguments' => [$document->uri, $line, strlen($value)],
            ],
        ];
    }

    /**
     * Determine if the document is an env file.
     */
    protected function isEnvDocument(Document $document): bool
    {
        return str_contains(FileUri::of($document->uri)->path(), '.env');
    }

    /**
     * Get selected env variable names from the document.
     *
     * @param  array<string, mixed>  $range
     * @return array<int, string>
     */
    protected function selectedEnvVariables(Document $document, array $range): array
    {
        $start = $range['start'] ?? null;
        $end = $range['end'] ?? null;

        if (!is_array($start) || !is_array($end)) {
            return [];
        }

        $startLine = $start['line'] ?? null;
        $endLine = $end['line'] ?? null;

        if (!is_int($startLine) || !is_int($endLine)) {
            return [];
        }

        $lines = explode("\n", $document->content);
        $variables = [];

        for ($line = max(0, $startLine); $line <= min($endLine, count($lines) - 1); $line++) {
            $envVariable = trim(explode('=', $lines[$line], 2)[0]);

            if ($envVariable === '' || str_starts_with($envVariable, '#') || str_starts_with($envVariable, 'VITE_')) {
                continue;
            }

            $variables[] = $envVariable;
        }

        return $variables;
    }

    /**
     * Determine if the env variable is already present.
     *
     * @param  array<int, string>  $lines
     */
    protected function containsEnvVariable(array $lines, string $variable): bool
    {
        foreach ($lines as $line) {
            if (str_starts_with($line, $variable . '=')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the Vite env insertion point.
     *
     * @param  array<int, string>  $lines
     * @return array{0: int, 1: string}
     */
    protected function viteInsertionPoint(array $lines): array
    {
        $lineNumber = count($lines);
        $foundGroup = false;

        foreach ($lines as $index => $line) {
            if (str_starts_with($line, 'VITE_')) {
                $lineNumber = $index + 1;
                $foundGroup = true;
            }
        }

        return [$lineNumber, $lineNumber === count($lines) || !$foundGroup ? "\n" : ''];
    }

    /**
     * Get code actions for the given diagnostic.
     *
     * @param  array<string, mixed>  $diagnostic
     * @return array<int, array<string, mixed>>
     */
    protected function actionsFor(Document $document, array $diagnostic): array
    {
        $range = $diagnostic['range'] ?? null;

        if (!is_array($range)) {
            return [];
        }

        $missing = $document->textInRange($range);

        if ($missing === '') {
            return [];
        }

        return collect([
            $this->addToEnv($missing, $diagnostic),
            $this->addFromExample($missing, $diagnostic),
        ])->filter()->values()->all();
    }

    /**
     * Create the add-to-env code action.
     *
     * @param  array<string, mixed>  $diagnostic
     * @return array<string, mixed>
     */
    protected function addToEnv(string $missing, array $diagnostic): array
    {
        return $this->insertEnvAction('Add variable to .env', $missing, '', $diagnostic, true);
    }

    /**
     * Create the add-from-env-example code action.
     *
     * @param  array<string, mixed>  $diagnostic
     * @return array<string, mixed>|null
     */
    protected function addFromExample(string $missing, array $diagnostic): ?array
    {
        $example = $this->envFileVariables($this->workspace->path('.env.example'));

        if (!array_key_exists($missing, $example)) {
            return null;
        }

        return $this->insertEnvAction(
            'Add value from .env.example',
            $missing,
            $example[$missing],
            $diagnostic,
            false,
        );
    }

    /**
     * Create a code action that inserts the env variable.
     *
     * @param  array<string, mixed>  $diagnostic
     * @return array<string, mixed>
     */
    protected function insertEnvAction(
        string $title,
        string $missing,
        string $value,
        array $diagnostic,
        bool $preferred,
    ): array {
        [$line, $prefix] = $this->insertionPoint($missing);

        $uri = (string) FileUri::fromPath($this->workspace->path('.env'));
        $value = "{$missing}={$value}\n";

        return [
            'title'       => $title,
            'kind'        => 'quickfix',
            'diagnostics' => [$diagnostic],
            'isPreferred' => $preferred,
            'edit'        => [
                'changes' => [
                    $uri => [[
                        'range' => [
                            'start' => [
                                'line'      => $line,
                                'character' => 0,
                            ],
                            'end' => [
                                'line'      => $line,
                                'character' => 0,
                            ],
                        ],
                        'newText' => $prefix . $value,
                    ]],
                ],
            ],
            'command' => [
                'title'     => 'Open file',
                'command'   => 'laravel.open',
                'arguments' => [$uri, $line, strlen($value)],
            ],
        ];
    }

    /**
     * Get the .env insertion point for the missing variable.
     *
     * @return array{0: int, 1: string}
     */
    protected function insertionPoint(string $missing): array
    {
        $lines = $this->envLines($this->workspace->path('.env'));
        $variablePrefix = explode('_', $missing)[0] . '_';
        $lineNumber = count($lines);
        $foundGroup = false;

        foreach ($lines as $index => $line) {
            if (str_starts_with($line, $variablePrefix)) {
                $lineNumber = $index + 1;
                $foundGroup = true;
            }
        }

        return [$lineNumber, $foundGroup ? '' : "\n"];
    }

    /**
     * Get lines from an env file.
     *
     * @return array<int, string>
     */
    protected function envLines(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        return explode("\n", (string) file_get_contents($path));
    }

    /**
     * Get env variables from an env file.
     *
     * @return array<string, string>
     */
    protected function envFileVariables(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $variables = [];

        foreach (explode("\n", (string) file_get_contents($path)) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');

            $variables[trim($key)] = trim($value);
        }

        return $variables;
    }
}
