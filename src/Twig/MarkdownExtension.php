<?php

namespace BestIt\ContentfulBundle\Twig;

use BestIt\ContentfulBundle\Service\MarkdownParser;
use Twig_Extension;
use Twig_SimpleFilter;

/**
 * Twig extension to render the markdown.
 *
 * @author lange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle\Twig
 * @subpackage Service
 */
class MarkdownExtension extends Twig_Extension
{
    /**
     * The markdown parser.
     *
     * @var MarkdownParser
     */
    protected $parser;

    /**
     * MarkdownExtension constructor.
     *
     * @param MarkdownParser $parser
     */
    public function __construct(MarkdownParser $parser)
    {
        $this->setParser($parser);
    }

    /**
     * Returns the filters for twig.
     *
     * @return Twig_SimpleFilter[]
     */
    public function getFilters(): array
    {
        return [new Twig_SimpleFilter('parseMarkdown', [$this, 'parseMarkdown'], ['is_safe' => ['html']])];
    }

    /**
     * Returns the name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'best_it_contentful_markdown_extension';
    }

    /**
     * Returns the markdown parser.
     *
     * @return MarkdownParser
     */
    protected function getParser(): MarkdownParser
    {
        return $this->parser;
    }

    /**
     * Sets the markdown parser.
     *
     * @param MarkdownParser $parser
     *
     * @return MarkdownExtension
     */
    protected function setParser(MarkdownParser $parser): MarkdownExtension
    {
        $this->parser = $parser;

        return $this;
    }

    /**
     * Returns the rendered html.
     *
     * @param mixed $content
     *
     * @return string
     */
    public function parseMarkdown($content): string
    {
        return $content ? $this->getParser()->toHtml($content) : '';
    }
}
