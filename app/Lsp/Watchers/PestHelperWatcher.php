<?php

declare(strict_types=1);

namespace App\Lsp\Watchers;

use App\Lsp\Contracts\FileWatcher;
use App\Lsp\Project;
use Throwable;

class PestHelperWatcher implements FileWatcher
{
    /**
     * Create a new Pest helper watcher instance.
     */
    public function __construct(protected Project $project)
    {
        //
    }

    /**
     * Get Pest helper watcher patterns.
     *
     * @return array<int, string>
     */
    public function patterns(): array
    {
        if (!$this->enabled()) {
            return [];
        }

        return [
            'tests/**/*',
            'vendor/composer/autoload_*.php',
        ];
    }

    /**
     * Generate the Pest helper file after watcher registration.
     */
    public function initialize(): void
    {
        $this->generate();
    }

    /**
     * Handle changed workspace-relative paths.
     *
     * @param  array<int, string>  $changes
     */
    public function onFileChange(array $changes): void
    {
        $this->generate();
    }

    /**
     * Determine if Pest helper docblocks should be generated.
     */
    protected function enabled(): bool
    {
        return $this->project->boolean('pestGenerateDocBlocks', true);
    }

    /**
     * Generate the Pest helper file.
     */
    protected function generate(): void
    {
        if (!$this->enabled()) {
            return;
        }

        try {
            $pest = $this->project->scripts->json($this->template());

            if (!is_array($pest)) {
                return;
            }

            $uses = $pest['uses'];
            $expectations = $pest['expectations'];

            if ($uses === [] && $expectations === []) {
                return;
            }

            $helperFilePath = $this->helperFilePath();

            if (!is_dir(dirname($helperFilePath))) {
                mkdir(dirname($helperFilePath), 0777, true);
            }

            file_put_contents($helperFilePath, $this->renderDocBlockFile([
                $this->renderFunctionStub($uses),
                ...$this->renderVirtualTestCases($uses),
                ...$this->renderExpectations($expectations),
            ]));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Get the Pest discovery template.
     */
    protected function template(): string
    {
        return file_get_contents(__DIR__ . '/../Data/Templates/pest.php') ?: '';
    }

    /**
     * Get the configured Pest helper file path.
     */
    protected function helperFilePath(): string
    {
        $options = $this->project->all();
        $relativePath = $options['pestHelperFilePath'] ?? 'storage/framework/testing/_pest.php';

        return $this->project->path(is_string($relativePath) ? $relativePath : 'storage/framework/testing/_pest.php');
    }

    /**
     * Render the Pest function stubs.
     *
     * @param  array<int, array<string, mixed>>  $uses
     */
    protected function renderFunctionStub(array $uses): string
    {
        $unionType = collect($uses)
            ->flatMap(function (array $use): array {
                $baseClass = $use['classes'][0] ?? 'PHPUnit\\Framework\\TestCase';
                $baseType = str_starts_with($baseClass, '\\') ? $baseClass : '\\' . $baseClass;

                return $use['traits'] === []
                    ? [$baseType]
                    : [$baseType, '\\' . $this->pestNamespace($use['path']) . '\\TestCase'];
            })
            ->unique()
            ->values()
            ->join('|');

        return "namespace {

    /**
     * Runs the given closure before each test in the current file.
     *
     * @param-closure-this {$unionType}  \$closure
     */
    function beforeEach(?Closure \$closure = null): \\Pest\\PendingCalls\\BeforeEachCall {}

    /**
     * Runs the given closure after each test in the current file.
     *
     * @param-closure-this {$unionType}  \$closure
     */
    function afterEach(?Closure \$closure = null): \\Pest\\PendingCalls\\AfterEachCall {}

    /**
     * Adds the given closure as a test.
     *
     * @param-closure-this {$unionType}  \$closure
     */
    function test(?string \$description = null, ?Closure \$closure = null): \\Pest\\Support\\HigherOrderTapProxy|\\Pest\\PendingCalls\\TestCall {}

    /**
     * Adds the given closure as a test.
     *
     * @param-closure-this {$unionType}  \$closure
     */
    function it(string \$description, ?Closure \$closure = null): \\Pest\\PendingCalls\\TestCall {}

}";
    }

    /**
     * Render virtual Pest test case classes for trait-backed uses.
     *
     * @param  array<int, array<string, mixed>>  $uses
     * @return array<int, string>
     */
    protected function renderVirtualTestCases(array $uses): array
    {
        return collect($uses)
            ->flatMap(function (array $use): array {
                if ($use['traits'] === []) {
                    return [];
                }

                $traitUses = collect($use['traits'])
                    ->map(fn (string $trait): string => $this->indent("use \\{$trait};", 2))
                    ->join(PHP_EOL);

                return [
                    "namespace {$this->pestNamespace($use['path'])} {

    class TestCase {
{$traitUses}
    }

}",
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Render Pest expectation stubs.
     *
     * @param  array<int, array<string, mixed>>  $expectations
     * @return array<int, string>
     */
    protected function renderExpectations(array $expectations): array
    {
        if ($expectations === []) {
            return [];
        }

        $methods = collect($expectations)
            ->map(function (array $expectation): string {
                return $this->indent(" * @method self {$expectation['name']}({$expectation['parameters']})");
            })
            ->join(PHP_EOL);

        return collect([
            ['Pest', 'Expectation'],
            ['Pest\\Expectations', 'OppositeExpectation'],
        ])->map(fn (array $class): string => "namespace {$class[0]} {

    /**
{$methods}
     */
    class {$class[1]} {}

}")->all();
    }

    /**
     * Render the generated Pest helper file.
     *
     * @param  array<int, string>  $blocks
     */
    protected function renderDocBlockFile(array $blocks): string
    {
        $header = implode(PHP_EOL, [
            '<?php',
            '',
            '/**',
            ' * This file is auto-generated by the Laravel VS Code extension.',
            ' * Do not modify this file directly as your changes will be overwritten.',
            ' */',
        ]);

        return collect([$header, ...$blocks])->join(PHP_EOL . PHP_EOL);
    }

    /**
     * Get the generated namespace for a Pest path.
     */
    protected function pestNamespace(string $filePath): string
    {
        $relative = preg_replace('/^tests[\/\\\\]?/', '', $filePath) ?? '';
        $segment = $relative !== '' ? str_replace(['/', '\\'], '\\', $relative) : 'Global';

        return "_Pest\\{$segment}";
    }

    /**
     * Indent a line of generated PHP.
     */
    protected function indent(string $text, int $repeat = 1): string
    {
        return str_repeat(' ', 4 * $repeat) . $text;
    }
}
