<?php

namespace Oro\Bundle\SearchBundle\Engine;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityBundle\ORM\OroEntityManager;
use Oro\Bundle\SearchBundle\Entity\Item;
use Oro\Bundle\SearchBundle\Entity\Repository\SearchIndexRepository;
use Oro\Bundle\SearchBundle\Query\LazyResult;
use Oro\Bundle\SearchBundle\Query\Query;
use Oro\Bundle\SearchBundle\Query\Result\Item as ResultItem;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * ORM standard search engine
 */
class Orm extends AbstractEngine
{
    const ENGINE_NAME = 'orm';

    /** @var SearchIndexRepository */
    private $indexRepository;

    /** @var OroEntityManager */
    private $indexManager;

    /** @var ObjectMapper */
    protected $mapper;

    public function __construct(
        ManagerRegistry $registry,
        ObjectMapper $mapper,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($registry, $eventDispatcher);

        $this->mapper = $mapper;
    }

    #[\Override]
    protected function doSearch(Query $query)
    {
        $resultsCallback = function () use ($query) {
            $results = [];
            $searchResults = $this->getIndexRepository()->search($query);
            if ($searchResults) {
                foreach ($searchResults as $item) {
                    $originalItem = $item;
                    if (is_array($item)) {
                        $item = $item['item'];
                    }

                    $results[] = new ResultItem(
                        $item['entity'],
                        $item['recordId'],
                        null,
                        $this->mapper->mapSelectedData($query, $originalItem),
                        $this->mapper->getEntityConfig($item['entity'])
                    );
                }
            }

            return $results;
        };

        $recordsCountCallback = function () use ($query) {
            return $this->getIndexRepository()->getRecordsCount($query);
        };

        $aggregatedDataCallback = function () use ($query) {
            return $this->getIndexRepository()->getAggregatedData($query);
        };

        return [
            'results' => $resultsCallback,
            'records_count' => $recordsCountCallback,
            'aggregated_data' => $aggregatedDataCallback,
        ];
    }

    /**
     * @param Query $query
     *
     * @return array
     * [
     *  <Entity ClassName> => <Documents Count>
     * ]
     */
    #[\Override]
    protected function doGetDocumentsCountGroupByEntityFQCN(Query $query): array
    {
        return $this->getIndexRepository()->getDocumentsCountGroupByEntityFQCN($query);
    }

    #[\Override]
    protected function buildResult(Query $query, array $data)
    {
        return new LazyResult(
            $query,
            $data['results'],
            $data['records_count'],
            $data['aggregated_data']
        );
    }

    /**
     * Get search index repository
     *
     * @return SearchIndexRepository
     */
    protected function getIndexRepository()
    {
        if ($this->indexRepository) {
            return $this->indexRepository;
        }

        $this->indexRepository = $this->getIndexManager()->getRepository(Item::class);

        return $this->indexRepository;
    }

    /**
     * Get search index repository
     *
     * @return OroEntityManager
     */
    protected function getIndexManager()
    {
        if ($this->indexManager) {
            return $this->indexManager;
        }

        $this->indexManager = $this->registry->getManagerForClass(Item::class);

        return $this->indexManager;
    }
}
