<?php

namespace BestIt\ContentfulBundle\Tests\Service\Cache;

use BestIt\ContentfulBundle\Service\Cache\FileSystemQueryStorage;
use Contentful\Delivery\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class FileSystemQueryStorageTest extends TestCase
{
    public function testThatStorageIsWorking()
    {
        $fixture = new FileSystemQueryStorage(
            __DIR__ . '/query.json',
            new Filesystem()
        );

        for ($i = 1; $i <= 6; $i++) {
            ${'query' . $i} = new Query();
            ${'originalQuery' . $i} = new Query();
            ${'query' . $i}->setContentType('Type_' . $i);
            ${'query' . $i}->select(['sys.id']);
            ${'originalQuery' . $i}->setContentType('Type_' . $i);
        }

        $fixture->saveQueryInStorage($query1, $originalQuery1, 'cacheId1');
        $fixture->saveQueryInStorage($query2, $originalQuery2, 'cacheId2');
        $fixture->saveQueryInStorage($query3, $originalQuery3);
        $fixture->saveQueryInStorage($query4, $originalQuery4);
        $fixture->saveQueryInStorage($query5, $originalQuery5);
        $fixture->saveQueryInStorage($query6, $originalQuery6);

        $queries = $fixture->getQueries();
        static::assertCount(8, $queries);
        static::assertSame([
            'cacheId1',
            '12f5104a055496a8ba7317a11d4d4fa7_Ids',
            'cacheId2',
            'db79db99f25360bde4aaa5f8c26072ef_Ids',
            'ba8893e3ba5be46f1eb473f27682abc4_Ids',
            'e5f510dc40026e9efb7f6df8288d45ca_Ids',
            '05c87864962f7ac9c857819580961cf3_Ids',
            '022b0ccadddd20b24f935a1ceda8f9e3_Ids'
        ], array_keys($queries));

        static::assertSame($query1->getQueryString(), $queries['cacheId1']->getQueryString());
        static::assertSame($query1->getQueryString(), $queries['12f5104a055496a8ba7317a11d4d4fa7_Ids']->getQueryString());
        static::assertSame($query2->getQueryString(), $queries['cacheId2']->getQueryString());
        static::assertSame($query2->getQueryString(), $queries['db79db99f25360bde4aaa5f8c26072ef_Ids']->getQueryString());
        static::assertSame($query3->getQueryString(), $queries['ba8893e3ba5be46f1eb473f27682abc4_Ids']->getQueryString());
        static::assertSame($query4->getQueryString(), $queries['e5f510dc40026e9efb7f6df8288d45ca_Ids']->getQueryString());
        static::assertSame($query5->getQueryString(), $queries['05c87864962f7ac9c857819580961cf3_Ids']->getQueryString());
        static::assertSame($query6->getQueryString(), $queries['022b0ccadddd20b24f935a1ceda8f9e3_Ids']->getQueryString());
    }

    protected function tearDown()
    {
        unlink(__DIR__ . '/query.json');
        parent::tearDown();
    }
}
