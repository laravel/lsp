<?php

namespace App\Parsers;

use App\Contexts\AbstractContext;
use App\Contexts\Blade;
use App\Parser\Parse;
use App\Parser\Settings;
use Microsoft\PhpParser\Node\Statement\InlineHtml;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\PositionUtilities;
use Microsoft\PhpParser\Range;
use Stillat\BladeParser\Document\Document;
use Stillat\BladeParser\Nodes\BaseNode;
use Stillat\BladeParser\Nodes\Components\ComponentNode;
use Stillat\BladeParser\Nodes\Components\ParameterNode;
use Stillat\BladeParser\Nodes\Components\ParameterType;
use Stillat\BladeParser\Nodes\DirectiveNode;
use Stillat\BladeParser\Nodes\EchoNode;
use Stillat\BladeParser\Nodes\EchoType;
use Stillat\BladeParser\Nodes\LiteralNode;

class InlineHtmlParser extends AbstractParser
{
    /**
     * Component tag prefixes that pass PHP expressions in their parameters,
     * next to the standard x- components the Blade parser handles natively.
     */
    protected const CUSTOM_COMPONENT_TAGS = ['flux', 'livewire'];

    protected $echoStrings = [
        '{!!' => '!!}',
        '{{{' => '}}}',
        '{{'  => '}}',
    ];

    protected $startLine = 0;

    /**
     * @var Blade
     */
    protected AbstractContext $context;

    protected array $items = [];

    /**
     * Stillat\BladeParser\Document\Document::fromText treats multibyte characters
     * as indentations and spaces resulting in a miscalculated Node position.
     *
     * This function replaces the multibyte characters with a single, placeholder character
     */
    private function replaceMultibyteChars(string $text, string $placeholder = '*'): string
    {
        return preg_replace('/[^\x00-\x7F]/u', $placeholder, $text);
    }

    public function parse(InlineHtml $node)
    {
        if ($node->getStartPosition() > 0) {
            $range = PositionUtilities::getRangeFromPosition(
                $node->getStartPosition(),
                mb_strlen($node->getText()),
                $node->getRoot()->getFullText(),
            );

            $this->startLine = $range->start->line;
        }

        $this->parseBladeContent(Document::fromText(
            $this->replaceMultibyteChars($node->getText()),
            customComponentTags: self::CUSTOM_COMPONENT_TAGS,
        ));

        if (count($this->items)) {
            $blade = new Blade;
            $this->context->initNew($blade);

            $blade->children = $this->items;

            return $blade;
        }

        return $this->context;
    }

    protected function parseBladeContent($node)
    {
        foreach ($node->getNodes() as $child) {
            // TODO: Add other echo types as well
            if ($child instanceof LiteralNode) {
                $this->parseLiteralNode($child);
            }

            if ($child instanceof DirectiveNode) {
                $this->parseBladeDirective($child);
            }

            if ($child instanceof EchoNode) {
                $this->parseEchoNode($child);
            }

            if ($child instanceof ComponentNode) {
                $this->parseComponentNode($child);
            }

            $this->parseBladeContent($child);
        }
    }

    protected function doEchoParse(BaseNode $node, $prefix, $content)
    {
        $snippet = "<?php\n" . str_repeat(' ', $node->getStartIndentationLevel()) . str_replace($prefix, '', $content) . ';';

        $sourceFile = (new Parser)->parseSourceFile($snippet);

        $suffix = $this->echoStrings[$prefix];

        Settings::$calculatePosition = function (Range $range) use ($node, $prefix, $suffix) {
            if ($range->start->line === 1) {
                $range->start->character += mb_strlen($prefix);
                $range->end->character += mb_strlen($suffix);
            }

            $range->start->line += $this->startLine + $node->position->startLine - 2;
            $range->end->line += $this->startLine + $node->position->startLine - 2;

            return $range;
        };

        $result = Parse::parse($sourceFile);

        if (count($result->children) === 0) {
            return;
        }

        $child = $result->children[0];

        $this->items[] = $child;
    }

    protected function parseLiteralNode(LiteralNode $node)
    {
        foreach ($this->echoStrings as $prefix => $suffix) {
            if (!str_starts_with($node->content, $prefix)) {
                continue;
            }

            $this->doEchoParse($node, $prefix, $node->content);
        }
    }

    protected function parseBladeDirective(DirectiveNode $node)
    {
        if ($node->isClosingDirective || !$node->hasArguments()) {
            return;
        }

        $methodUsed = '@' . $node->content;
        $safetyPrefix = 'directive';
        $snippet = "<?php\n" . str_repeat(' ', $node->getStartIndentationLevel()) . str_replace($methodUsed, $safetyPrefix . $node->content, $node->toString() . ';');

        $sourceFile = (new Parser)->parseSourceFile($snippet);

        Settings::$calculatePosition = function (Range $range) use ($node, $safetyPrefix) {
            if ($range->start->line === 1) {
                $range->start->character -= mb_strlen($safetyPrefix) - 1;
                $range->end->character -= mb_strlen($safetyPrefix) - 1;
            }

            $range->start->line += $this->startLine + $node->position->startLine - 2;
            $range->end->line += $this->startLine + $node->position->startLine - 2;

            return $range;
        };

        $result = Parse::parse($sourceFile);

        $child = $result->children[0];

        $child->methodName = '@' . substr($child->methodName, mb_strlen($safetyPrefix));

        $this->items[] = $child;
    }

    protected function parseComponentNode(ComponentNode $node)
    {
        foreach ($node->parameters as $parameter) {
            if ($parameter->value === '' || $parameter->valueNode?->position === null) {
                continue;
            }

            $line = $parameter->valueNode->position->startLine;
            $column = $this->parameterColumn($node, $parameter);

            if ($parameter->type === ParameterType::DynamicVariable) {
                $this->parseExpression($parameter->value, $line, $column);

                continue;
            }

            foreach ($this->parameterEchoes($parameter->value) as [$content, $offset]) {
                $this->parseExpression($content, $line, $column + $offset);
            }
        }
    }

    /**
     * Stillat v2.1 reports parameter positions relative to a normalized tag
     * (ComponentParser::getRelativeContentOffset): custom component tags are
     * shifted left by their prefix length ($node->componentPrefix holds the
     * prefix without the colon, e.g. "flux"), and paired standard tags are
     * off by one against their self-closing form. Shift the column back.
     */
    protected function parameterColumn(ComponentNode $node, ParameterNode $parameter): int
    {
        return $parameter->valueNode->position->startColumn + match (true) {
            $node->isCustomComponent => mb_strlen($node->componentPrefix),
            $node->isSelfClosing     => 0,
            default                  => 1,
        };
    }

    protected function parameterEchoes(string $value): array
    {
        $echoStrings = $this->echoStrings;
        uksort($echoStrings, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $alternations = [];

        foreach ($echoStrings as $prefix => $suffix) {
            $alternations[] = preg_quote($prefix, '/') . '(.*?)' . preg_quote($suffix, '/');
        }

        // The lookbehind skips escaped echoes such as @{{ alpine }}.
        preg_match_all(
            '/(?<!@)(?:' . implode('|', $alternations) . ')/s',
            $value,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
        );

        $echoes = [];

        foreach ($matches as $match) {
            foreach (array_slice($match, 1) as $capture) {
                if (($capture[1] ?? -1) !== -1) {
                    $echoes[] = [$capture[0], $capture[1]];
                }
            }
        }

        return $echoes;
    }

    protected function parseExpression(string $content, int $line, int $column)
    {
        $snippet = "<?php\n" . str_repeat(' ', $column) . $content . ';';

        $sourceFile = (new Parser)->parseSourceFile($snippet);

        Settings::$calculatePosition = function (Range $range) use ($line) {
            $range->start->line += $this->startLine + $line - 2;
            $range->end->line += $this->startLine + $line - 2;

            return $range;
        };

        $result = Parse::parse($sourceFile);

        if (count($result->children) === 0) {
            return;
        }

        $this->items[] = $result->children[0];
    }

    protected function parseEchoNode(EchoNode $node)
    {
        $prefix = match ($node->type) {
            EchoType::RawEcho    => '{!!',
            EchoType::TripleEcho => '{{{',
            default              => '{{',
        };

        $this->doEchoParse($node, $prefix, $node->innerContent);
    }
}
