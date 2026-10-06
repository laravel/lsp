<?php

namespace App\Parser;

use App\Contexts\AbstractContext;
use App\Contexts\Blade;
use App\Support\Debugs;
use Illuminate\Support\Arr;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Attribute;
use Microsoft\PhpParser\Node\Expression\CallExpression;
use Microsoft\PhpParser\Node\Expression\ObjectCreationExpression;
use Microsoft\PhpParser\Node\SourceFileNode;
use Microsoft\PhpParser\Parser;

class DetectWalker
{
    use Debugs;

    protected DetectContext $context;

    protected $items = [];

    protected $depth = 0;

    protected SourceFileNode $sourceFile;

    protected $postArgumentParsingCallback = null;

    protected $nextNodeToWalk = null;

    public function __construct(protected string $document, $debug = false)
    {
        $this->debug = $debug;
        $this->sourceFile = (new Parser)->parseSourceFile($this->document);
        $this->context = new DetectContext;
    }

    public function walk(?Node $node = null)
    {
        Settings::reset();
        Settings::$capturePosition = true;

        Parse::$debug = $this->debug;

        Parse::parse(
            node: $this->sourceFile,
            callback: $this->handleContext(...),
        );

        return collect($this->items)->map(fn ($item) => Arr::except($item->toArray(), 'children'));
    }

    protected function handleContext(Node $node, AbstractContext $context)
    {
        $nodesToDetect = [
            Attribute::class,
            CallExpression::class,
            ObjectCreationExpression::class,
        ];

        foreach ($nodesToDetect as $nodeClass) {
            if ($node instanceof $nodeClass) {
                $this->items[] = $context;

                $context->parent->children = array_filter($context->parent->children, fn ($child) => $child !== $context);
            }
        }

        if ($context instanceof Blade) {
            foreach ($context->children as $child) {
                $this->items[] = $child;
            }
        }
    }
}
