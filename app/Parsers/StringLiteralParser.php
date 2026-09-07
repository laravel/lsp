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
        $kind = $this->kind($node);

        if ($kind === 'heredoc' || $kind === 'nowdoc') {
            $contents = preg_replace('/\r?\n$/', '', $contents);
        }

        return match ($kind) {
            'nowdoc'  => $contents,
            'single'  => strtr($contents, ['\\\\' => '\\', "\\'" => "'"]),
            'heredoc' => $this->unescape($contents, false),
            default   => $this->unescape($contents, true),
        };
    }

    /**
     * Get the kind of string literal the node holds.
     */
    protected function kind(StringLiteral $node): string
    {
        if (($node->startQuote->kind ?? null) !== TokenKind::HeredocStart) {
            return ($node->getRoot()->getFullText()[$node->getStartPosition()] ?? '"') === "'"
                ? 'single'
                : 'double';
        }

        $start = substr($node->getRoot()->getFullText(), $node->getStartPosition(), 32);

        return preg_match('/^<<<[ \t]*\'/', $start) === 1 ? 'nowdoc' : 'heredoc';
    }

    /**
     * Resolve the escape sequences PHP evaluates in a double quoted string.
     *
     * A heredoc uses the same rules except that a quote is never escaped,
     * so a backslash before one is kept.
     */
    protected function unescape(string $contents, bool $escapesQuote): string
    {
        return preg_replace_callback(
            '/\\\\(u\{[0-9A-Fa-f]+\}|x[0-9A-Fa-f]{1,2}|[0-7]{1,3}|.)/s',
            function (array $matches) use ($escapesQuote): string {
                $sequence = $matches[1];

                if (str_starts_with($sequence, 'u{')) {
                    return mb_chr((int) hexdec(substr($sequence, 2, -1)), 'UTF-8') ?: $matches[0];
                }

                if ($sequence[0] === 'x' && strlen($sequence) > 1) {
                    return chr((int) hexdec(substr($sequence, 1)));
                }

                if (preg_match('/^[0-7]{1,3}$/', $sequence) === 1) {
                    return chr((int) octdec($sequence) % 256);
                }

                return match (true) {
                    $sequence === 'n'                  => "\n",
                    $sequence === 'r'                  => "\r",
                    $sequence === 't'                  => "\t",
                    $sequence === 'v'                  => "\v",
                    $sequence === 'e'                  => "\e",
                    $sequence === 'f'                  => "\f",
                    $sequence === '\\'                 => '\\',
                    $sequence === '$'                  => '$',
                    $sequence === '"' && $escapesQuote => '"',
                    default                            => $matches[0],
                };
            },
            $contents
        );
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
