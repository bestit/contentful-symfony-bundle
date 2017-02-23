<?php

namespace BestIt\ContentfulBundle\Twig;

use BestIt\ContentfulBundle\Service\Delivery\ClientDecorator;
use Contentful\Delivery\Query;
use Twig_Extension;
use Twig_SimpleFunction;

/**
 * Makes contentful entries directly usable in the template.
 * @author blange <lange@bestit-online.de>
 * @package BestIt\ContentfulBundle
 * @subpackage Twig
 * @version $id$
 */
class ContentfulExtension extends Twig_Extension
{
    /**
     * The used contentful client.
     * @var ClientDecorator
     */
    private $client = null;

    /**
     * ContentfulExtension constructor.
     * @param ClientDecorator $clientDecorator
     */
    public function __construct(ClientDecorator $clientDecorator)
    {
        $this->setClient($clientDecorator);
    }

    /**
     * Returns the used contentful client.
     * @return ClientDecorator
     */
    private function getClient(): ClientDecorator
    {
        return $this->client;
    }

    /**
     * Returns contentful content to use directly in the template.
     * @param string|array $where The direct usable id or an array as where query.
     * @param string $attribute Which attribute should be returned. If no string given, the array itself is given ...
     * @param string $contentType The queried content type.
     * @param int $limit How many entries should be fetched on a where query.
     * @param string $default The default value if nothing is found.
     * @return array|mixed|string
     */
    public function getContentfulContent(
        $where,
        string $attribute = '',
        string $contentType = '',
        int $limit = 1,
        $default = ''
    ) {
        if (is_scalar($where)) {
            $result = $this->getClient()->getEntry($where);
        } else {
            $result = $this->getClient()->getEntries(
                function (Query $query) use ($contentType, $limit, $where) {
                    $query->setContentType($contentType);

                    if ($limit) {
                        $query->setLimit($limit);
                    }

                    array_walk($where, function ($value, $key) use ($query) {
                        $query->where($key, $value);
                    });

                    return $query;
                },
                sha1(__METHOD__ . ':' . $contentType . ':' . serialize($where))
            );
        }

        if ($attribute && $limit === 1) {
            if (!is_scalar($where)) {
                $result = reset($result) ?: [];
            }

            $result = $result[$attribute] ?? $default;
        }

        return $result ?: $default;
    }

    /**
     * Returns the name for this extension.
     * @return string
     */
    public function getName(): string
    {
        return 'best_it_contentful_contentful_extension';
    }

    /**
     * Returns helping functions.
     * @return Twig_SimpleFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new Twig_SimpleFunction(
                'get_contentful',
                [$this, 'getContentfulContent']
            ),
        ];
    }

    /**
     * Sets the used contentful client.
     * @param ClientDecorator $client
     * @return ContentfulExtension
     */
    private function setClient(ClientDecorator $client): ContentfulExtension
    {
        $this->client = $client;

        return $this;
    }
}
