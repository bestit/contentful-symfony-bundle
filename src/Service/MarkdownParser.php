<?php

namespace BestIt\ContentfulBundle\Service;

use Parsedown;

/**
 * Helper to render the markdown.
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 * @subpackage Service
 * @version $id$
 */
class MarkdownParser
{
    /**
     * The markdown parser.
     * @var Parsedown
     */
    protected $parser;

    /**
     * MarkdownParser constructor.
     */
    public function __construct()
    {
        $this->parser = new Parsedown();
    }

    /**
     * Returns the parser.
     * @return Parsedown
     */
    protected function getParser(): Parsedown
    {
        return $this->parser;
    }

    /**
     * Sets the parser.
     * @param Parsedown $parser
     * @return MarkdownParser
     */
    protected function setParser(Parsedown $parser): MarkdownParser
    {
        $this->parser = $parser;

        return $this;
    }

    /**
     * Parses the markdown.
     * @param string $text
     * @return string
     */
    public function toHtml(string $text): string
    {
        $html = $this->parser->text($text);

        return $html;
    }
}
