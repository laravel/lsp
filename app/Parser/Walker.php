<?php

namespace App\Parser;

use App\Contexts\Base;
use App\Support\Debugs;
use Microsoft\PhpParser\Node\SourceFileNode;
use Microsoft\PhpParser\Node\Statement\InlineHtml;
use Microsoft\PhpParser\Parser;
use Microsoft\PhpParser\SkippedToken;

class Walker
{
    use Debugs;

    protected Context $context;

    protected $depth = 0;

    protected SourceFileNode $sourceFile;

    protected $postArgumentParsingCallback = null;

    protected $nextNodeToWalk = null;

    public function __construct(protected string $document, $debug = false)
    {
        $this->debug = $debug;
        $this->sourceFile = (new Parser)->parseSourceFile($this->normalizeDocument($document));
        $this->context = new Context;
    }

    protected function documentSkipsClosingQuote()
    {
        if (count($this->sourceFile->statementList) === 1 && $this->sourceFile->statementList[0] instanceof InlineHtml) {
            // Probably Blade...
            $lastChar = substr($this->document, -1);
            $closesWithQuote = in_array($lastChar, ['"', "'"]) && substr_count($this->document, $lastChar) % 2 === 1;

            return $closesWithQuote;
        }

        foreach ($this->sourceFile->getDescendantNodesAndTokens() as $child) {
            if ($child instanceof SkippedToken && str_starts_with($child->getText($this->sourceFile->getFullText()), "'")) {
                return true;
            }
        }

        return false;
    }

    /**
     * If a last character is a double quote, for example:
     *
     * {{ config("
     *
     * then Microsoft\PhpParser\Parser::parseSourceFile returns autocompletingIndex: 1
     * instead 0. Probably the parser turns the string into something like this:
     *
     * "{{ config(";"
     *
     * and returns ";" as an argument.
     *
     * This line of code checks if the last character is a double quote and fixes it.
     */
    protected function normalizeDocument(string $document): string
    {
        $document = trim($document);

        if (str_ends_with($document, '"')) {
            return substr($document, 0, -1) . "'";
        }

        return $document;
    }

    public function walk()
    {
        Settings::reset();

        if (!$this->documentSkipsClosingQuote()) {
            return new Base;
        }

        Parse::$debug = $this->debug;

        return Parse::parse($this->sourceFile);
    }
}
