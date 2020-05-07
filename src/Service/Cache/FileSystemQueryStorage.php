<?php

declare(strict_types=1);

namespace BestIt\ContentfulBundle\Service\Cache;

use Contentful\Query;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Storage to save all used contentful queries via the file system
 *
 * @author Martin Knoop <martin.knoop@bestit-online.de>
 * @package BestIt\ContentfulBundle\Service\Cache
 */
class FileSystemQueryStorage implements QueryStorageInterface, LoggerAwareInterface
{
    use CacheKeyTrait;
    use LoggerAwareTrait;

    /**
     * The file where the queries are stored
     *
     * @var string
     */
    private $storageFile;

    /**
     * The filesystem for the file operations
     *
     * @var Filesystem
     */
    private $fileSystem;

    /**
     * FileSystemQueryStorage constructor.
     *
     * @param string $storageFile
     * @param Filesystem $fileSystem
     */
    public function __construct(string $storageFile, Filesystem $fileSystem)
    {
        $this->storageFile = $storageFile;
        $this->fileSystem = $fileSystem;

        $this->logger = new NullLogger();
    }

    /**
     * Get all stored queries, indexed by the cache id
     *
     * @return Query[]
     */
    public function getQueries(): array
    {
        $queries = [];
        foreach ($this->getSerializedQueries() as $cacheId => $query) {
            $queries[$cacheId] = unserialize($query, [Query::class]);
        }

        return $queries;
    }

    /**
     * Save a query in the storage with the given cache id
     *
     * @param Query $query The query that is used
     * @param Query $originalQuery The original query that is build, will be used to generate the cache ids
     * @param string $cacheId A custom cache id that is used
     *
     * @return void
     */
    public function saveQueryInStorage(Query $query, Query $originalQuery, string $cacheId = null)
    {
        $queryCacheId = $this->getQueryIdsCacheKey($originalQuery);

        $queries = $this->getSerializedQueries();

        $serializedQuery = serialize($query);
        if ($cacheId) {
            $queries[$cacheId] = $serializedQuery;
        }
        $queries[$queryCacheId] = $serializedQuery;

        $decodedQueries = json_encode($queries);

        $this->logger->debug(
            'Add query to query storage',
            [
                'query' => $originalQuery->getQueryString(),
                'cacheId' => $cacheId,
                'queryCacheId' => $queryCacheId
            ]
        );

        $this->fileSystem->dumpFile($this->storageFile, $decodedQueries);
    }

    /**
     * Get the list of decoded queries
     *
     * @return array
     */
    private function getSerializedQueries():array
    {
        $result = [];
        if ($this->fileSystem->exists($this->storageFile)) {
            $result = json_decode(file_get_contents($this->storageFile), true);
        }

        return $result;
    }
}
