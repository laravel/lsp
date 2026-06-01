<?php

declare(strict_types=1);

namespace App\Lsp\Features\Views;

use App\Lsp\CodeActions\CodeActionContext;
use App\Lsp\Contracts\CodeActionProvider;
use App\Lsp\Document;
use App\Lsp\Support\FileUri;
use App\Lsp\Project;

class ViewCodeActionProvider implements CodeActionProvider
{
    /**
     * Create a new view code action provider instance.
     */
    public function __construct(
        protected Project $project,
    ) {}

    /**
     * Provide view code actions for the given document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(Document $document, CodeActionContext $context): array
    {
        if (!$context->accepts('quickfix')) {
            return [];
        }

        return $context->diagnostics('view')
            ->flatMap(fn (array $diagnostic): array => $this->actionsFor($document, $diagnostic))
            ->values()
            ->all();
    }

    /**
     * Get code actions for the given diagnostic.
     *
     * @param  array<string, mixed>  $diagnostic
     * @return array<int, array<string, mixed>>
     */
    protected function actionsFor(Document $document, array $diagnostic): array
    {
        $missing = $document->textInRange($diagnostic['range']);

        if ($missing === '') {
            return [];
        }

        return [$this->createViewAction($missing, $diagnostic)];
    }

    /**
     * Create the view file code action.
     *
     * @param  array<string, mixed>  $diagnostic
     * @return array<string, mixed>
     */
    protected function createViewAction(string $missing, array $diagnostic): array
    {
        $relativePath = 'resources/views/' . str_replace('.', '/', $missing) . '.blade.php';
        $uri = (string) FileUri::fromPath($this->project->path($relativePath));

        return [
            'title'       => 'Create missing view',
            'kind'        => 'quickfix',
            'diagnostics' => [$diagnostic],
            'isPreferred' => true,
            'edit'        => [
                'documentChanges' => [[
                    'kind'    => 'create',
                    'uri'     => $uri,
                    'options' => [
                        'overwrite' => false,
                    ],
                ]],
            ],
            'command' => [
                'title'     => 'Open file',
                'command'   => 'laravel.open',
                'arguments' => [$uri, 1, 0],
            ],
        ];
    }
}
