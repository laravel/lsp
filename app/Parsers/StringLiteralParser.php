<?php

namespace App\Parsers;

use App\Contexts\AbstractContext;
use App\Contexts\StringValue;
use App\Parser\Settings;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\StringLiteral;
use Microsoft\PhpParser\PositionUtilities;
use Microsoft\PhpParser\TokenKind;

class StringLiteralParser extends AbstractParser
{
    /**
     * @var StringValue
     */
    protected AbstractContext $context;

    public function parse(StringLiteral $node)
    {
        $contents = $this->contents($node);

        $this->context->value = $contents;
        $this->context->interpolated = $this->isInterpolated($node);

        if (Settings::$capturePosition) {
            $range = PositionUtilities::getRangeFromPosition(
                $node->getStartPosition(),
                mb_strlen($contents),
                $node->getRoot()->getFullText(),
            );

            if (Settings::$calculatePosition !== null) {
                $range = Settings::adjustPosition($range);
            }

            $this->context->setPosition($range);
        }

        return $this->context;
    }

    /**
     * Get the contents of the string.
     *
     * A heredoc or nowdoc body ends with the newline that precedes its
     * closing identifier, which PHP does not include in the value.
     */
    protected function contents(StringLiteral $node): string
    {
        $contents = $node->getStringContentsText();

        if (($node->startQuote->kind ?? null) !== TokenKind::HeredocStart) {
            return $contents;
        }

        return preg_replace('/\r?\n$/', '', $contents);
    }

    /**
     * Determine if the string contains an interpolated expression.
     *
     * The contents are read straight from the source, so an interpolated
     * string yields the expression verbatim rather than its value. Only
     * interpolation produces child nodes; escaped dollars, single quoted
     * strings and nowdocs are plain tokens.
     */
    protected function isInterpolated(StringLiteral $node): bool
    {
        if (!is_array($node->children)) {
            return false;
        }

        foreach ($node->children as $child) {
            if ($child instanceof Node) {
                return true;
            }
        }

        return false;
    }

    public function initNewContext(): ?AbstractContext
    {
        return new StringValue;
    }
}
